<?php

namespace App\Domain\Fulfillment\Services;

use App\Domain\Commerce\Enums\FulfillmentStatus;
use App\Domain\Commerce\Models\Order;
use App\Domain\Fulfillment\Data\ShipmentMutationResult;
use App\Domain\Fulfillment\Enums\ShipmentStatus;
use App\Domain\Fulfillment\Models\Shipment;
use App\Domain\Fulfillment\Models\ShipmentOperation;
use App\Domain\Fulfillment\Models\ShippingCarrier;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FulfillmentService
{
    private const NEXT=['unfulfilled'=>['processing'],'processing'=>['printing','on_hold'],'printing'=>['ready_to_ship','on_hold'],'ready_to_ship'=>['fulfilled','on_hold'],'on_hold'=>['processing','printing','ready_to_ship','cancelled'],'fulfilled'=>[],'cancelled'=>[]];
    private const HANDED_STATUSES=['shipped','in_transit','delivered','exception','returned'];

    public function __construct(private ShippingProviderRegistry $providers) {}

    public function transition(Order $order,FulfillmentStatus $to,?int $actorId=null,?string $reason=null):Order
    {
        return DB::transaction(function()use($order,$to,$actorId,$reason){
            $o=Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if(!$o->payment_status->allowsFulfillment())throw new RuntimeException('Only paid or partially refunded orders can enter fulfillment.');
            $from=$o->fulfillment_status;
            if(!in_array($to->value,self::NEXT[$from->value]??[],true))throw new RuntimeException("Invalid fulfillment transition: {$from->value} -> {$to->value}.");
            $this->transitionLocked($o,$to,$actorId,$reason);
            return $o->refresh();
        },3);
    }

    public function createManualShipment(Order $order,array $data,int $actorId):ShipmentMutationResult
    {
        $requestedItems=collect($data['items']);
        if($requestedItems->pluck('order_item_id')->unique()->count()!==$requestedItems->count())throw new RuntimeException('Each order item may appear only once in a shipment request.');
        $normalizedItems=$requestedItems->mapWithKeys(fn($row)=>[(int)$row['order_item_id']=>(int)$row['quantity']])->filter(fn($qty)=>$qty>0);
        $fingerprint=hash('sha256',json_encode([$order->id,(int)$data['shipping_carrier_id'],$normalizedItems->sortKeys()->all(),$data['tracking_number']??null,$data['tracking_url']??null],JSON_THROW_ON_ERROR));
        try {
            return DB::transaction(function()use($order,$data,$actorId,$normalizedItems,$fingerprint){
                $o=Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
                if(!$o->payment_status->allowsFulfillment())throw new RuntimeException('Cannot prepare a shipment for an unpaid or fully refunded order.');
                if(!in_array($o->fulfillment_status,[FulfillmentStatus::ReadyToShip,FulfillmentStatus::Fulfilled],true))throw new RuntimeException('Order must be ready to ship.');
                $carrier=ShippingCarrier::query()->whereKey($data['shipping_carrier_id'])->where('active',true)->firstOrFail();
                $items=$normalizedItems;
                if($items->isEmpty())throw new RuntimeException('Select at least one item quantity for this shipment.');
                $existing=Shipment::query()->where('idempotency_key',$data['idempotency_key'])->first();
                if($existing){
                    if($existing->order_id!==$o->id||!hash_equals((string)$existing->request_fingerprint,$fingerprint))throw new RuntimeException('This shipment request key was already used for different data.');
                    return new ShipmentMutationResult($existing->load(['items.orderItem','events','carrier']),false);
                }
                $o->loadMissing('items');
                foreach($items as $itemId=>$quantity){
                    $item=$o->items->firstWhere('id',$itemId);
                    if(!$item)throw new RuntimeException('A selected item does not belong to this order.');
                    $allocated=(int)DB::table('shipment_items')->join('shipments','shipments.id','=','shipment_items.shipment_id')->where('shipment_items.order_item_id',$itemId)->where('shipments.status','!=',ShipmentStatus::Cancelled->value)->sum('shipment_items.quantity');
                    if($quantity>$item->quantity-$allocated)throw new RuntimeException("Shipment quantity exceeds the remaining quantity for {$item->product_name}.");
                }
                $trackingUrl=$this->trackingUrl($data['tracking_url']??null,$carrier->tracking_url_template,$data['tracking_number']??null);
                $shipment=Shipment::create([
                    'order_id'=>$o->id,'shipping_method_id'=>$o->shipping_method_id,'shipping_carrier_id'=>$carrier->id,
                    'provider'=>'manual','carrier_name'=>$carrier->name,'carrier_code'=>$carrier->code,
                    'idempotency_key'=>$data['idempotency_key'],'request_fingerprint'=>$fingerprint,'created_by'=>$actorId,
                    'status'=>ShipmentStatus::Pending,'tracking_number'=>$data['tracking_number']??null,'tracking_url'=>$trackingUrl,
                    'shipping_cost'=>0,'currency'=>$o->currency,
                ]);
                $provider=$this->providers->driver('manual')->createShipment($shipment);
                $shipment->update(['provider_shipment_id'=>$provider->id]);
                foreach($items as $itemId=>$quantity)$shipment->items()->create(['order_item_id'=>$itemId,'quantity'=>$quantity]);
                $this->event($shipment,ShipmentStatus::Pending,'Manual shipment draft created.',$actorId,['carrier_code'=>$carrier->code]);
                return new ShipmentMutationResult($shipment->load(['items.orderItem','events','carrier']),true);
            },3);
        } catch(QueryException $exception) {
            if(!str_contains(strtolower($exception->getMessage()),'unique'))throw $exception;
            $existing=Shipment::query()->where('idempotency_key',$data['idempotency_key'])->first();
            if(!$existing)throw $exception;
            if($existing->order_id!==$order->id||!hash_equals((string)$existing->request_fingerprint,$fingerprint))throw new RuntimeException('This shipment request key was already used for different data.');
            return new ShipmentMutationResult($existing->load(['items.orderItem','events','carrier']),false);
        }
    }

    public function updateDraft(Shipment $shipment,array $data,int $actorId):ShipmentMutationResult
    {
        return $this->operate($shipment,$data['operation_key'],'update',$data,$actorId,function(Shipment $locked)use($data,$actorId){
            if($locked->status!==ShipmentStatus::Pending)throw new RuntimeException('Only a pending shipment draft can be edited.');
            $carrier=ShippingCarrier::query()->whereKey($data['shipping_carrier_id'])->where('active',true)->firstOrFail();
            $url=$this->trackingUrl($data['tracking_url']??null,$carrier->tracking_url_template,$data['tracking_number']??null);
            $locked->update(['shipping_carrier_id'=>$carrier->id,'carrier_name'=>$carrier->name,'carrier_code'=>$carrier->code,'tracking_number'=>$data['tracking_number']??null,'tracking_url'=>$url]);
            $this->event($locked,ShipmentStatus::Pending,'Manual shipment details updated.',$actorId,['carrier_code'=>$carrier->code]);
        });
    }

    public function handOver(Shipment $shipment,array $data,int $actorId):ShipmentMutationResult
    {
        return $this->operate($shipment,$data['operation_key'],'hand_over',$data,$actorId,function(Shipment $locked)use($data,$actorId){
            if($locked->status===ShipmentStatus::Shipped)throw new RuntimeException('This shipment has already been handed to the carrier.');
            if($locked->status!==ShipmentStatus::Pending)throw new RuntimeException('Only a pending shipment can be handed to the carrier.');
            $at=isset($data['shipped_at'])?\Illuminate\Support\Carbon::parse($data['shipped_at']):now();
            if($at->isFuture())throw new RuntimeException('Carrier handover time cannot be in the future.');
            $locked->update(['status'=>ShipmentStatus::Shipped,'shipped_at'=>$at,'handed_over_by'=>$actorId]);
            $this->event($locked,ShipmentStatus::Shipped,'Shipment explicitly handed to the carrier.',$actorId);
            $this->syncOrderFulfillment($locked->order()->lockForUpdate()->firstOrFail(),$actorId);
        });
    }

    public function setOperationalStatus(Shipment $shipment,array $data,int $actorId):ShipmentMutationResult
    {
        return $this->operate($shipment,$data['operation_key'],'status',$data,$actorId,function(Shipment $locked)use($data,$actorId){
            $to=ShipmentStatus::from($data['status']);
            $allowed=['shipped'=>['in_transit','exception','delivered'],'in_transit'=>['exception','delivered'],'exception'=>['in_transit','delivered']];
            if(!in_array($to->value,$allowed[$locked->status->value]??[],true))throw new RuntimeException("Invalid shipment transition: {$locked->status->value} -> {$to->value}.");
            $updates=['status'=>$to];
            if($to===ShipmentStatus::Delivered){$updates['delivered_at']=now();$updates['delivered_by']=$actorId;}
            $locked->update($updates);
            $this->event($locked,$to,$to===ShipmentStatus::Delivered?'Delivery explicitly confirmed by staff.':'Shipment operational status updated by staff.',$actorId);
        });
    }

    public function cancelDraft(Shipment $shipment,array $data,int $actorId):ShipmentMutationResult
    {
        return $this->operate($shipment,$data['operation_key'],'cancel',$data,$actorId,function(Shipment $locked)use($data,$actorId){
            if($locked->status===ShipmentStatus::Cancelled)throw new RuntimeException('This shipment draft is already cancelled.');
            if($locked->status!==ShipmentStatus::Pending)throw new RuntimeException('A shipment can only be cancelled before carrier handover.');
            $this->providers->driver($locked->provider?:'manual')->cancel($locked);
            $locked->update(['status'=>ShipmentStatus::Cancelled,'cancelled_at'=>now(),'cancelled_by'=>$actorId,'cancellation_reason'=>$data['reason']]);
            $this->event($locked,ShipmentStatus::Cancelled,'Shipment draft cancelled; its quantities are available for a new shipment.',$actorId,['reason'=>$data['reason']]);
        });
    }

    private function operate(Shipment $shipment,string $key,string $action,array $payload,int $actorId,callable $operation):ShipmentMutationResult
    {
        return DB::transaction(function()use($shipment,$key,$action,$payload,$actorId,$operation){
            $locked=Shipment::query()->whereKey($shipment->id)->lockForUpdate()->firstOrFail();
            $hash=hash('sha256',json_encode($payload,JSON_THROW_ON_ERROR));
            $existing=ShipmentOperation::query()->where('operation_key',$key)->first();
            if($existing){
                if($existing->shipment_id!==$locked->id||$existing->action!==$action||!hash_equals($existing->payload_hash,$hash))throw new RuntimeException('This operation key was already used for a different request.');
                return new ShipmentMutationResult($locked->load(['items.orderItem','events','carrier']),false);
            }
            $operation($locked);
            ShipmentOperation::create(['shipment_id'=>$locked->id,'operation_key'=>$key,'action'=>$action,'payload_hash'=>$hash,'actor_user_id'=>$actorId,'created_at'=>now()]);
            return new ShipmentMutationResult($locked->refresh()->load(['items.orderItem','events','carrier']),true);
        },3);
    }

    private function syncOrderFulfillment(Order $order,int $actorId):void
    {
        $purchased=(int)$order->items()->sum('quantity');
        $handed=(int)DB::table('shipment_items')->join('shipments','shipments.id','=','shipment_items.shipment_id')->where('shipments.order_id',$order->id)->whereIn('shipments.status',self::HANDED_STATUSES)->sum('shipment_items.quantity');
        if($purchased>0&&$handed===$purchased&&$order->fulfillment_status===FulfillmentStatus::ReadyToShip)$this->transitionLocked($order,FulfillmentStatus::Fulfilled,$actorId,'all_quantities_handed_to_carrier');
    }

    private function trackingUrl(?string $explicit,?string $template,?string $trackingNumber):?string
    {
        $value=$explicit;
        if(!$value&&$template&&$trackingNumber)$value=str_replace('{tracking_number}',rawurlencode($trackingNumber),$template);
        if(!$value)return null;
        $parts=parse_url($value);
        if(($parts['scheme']??null)!=='https'||empty($parts['host'])||isset($parts['user'])||isset($parts['pass']))throw new RuntimeException('Tracking links must be credential-free HTTPS URLs.');
        return $value;
    }

    private function event(Shipment $shipment,ShipmentStatus $status,string $description,int $actorId,?array $metadata=null):void
    {
        $shipment->events()->create(['status'=>$status->value,'description'=>$description,'actor_user_id'=>$actorId,'metadata'=>$metadata,'occurred_at'=>now(),'created_at'=>now()]);
    }

    private function transitionLocked(Order $order,FulfillmentStatus $to,?int $actorId,?string $reason):void
    {
        $from=$order->fulfillment_status;
        $order->update(['fulfillment_status'=>$to]);
        $order->statusHistory()->create(['domain'=>'fulfillment','from_status'=>$from->value,'to_status'=>$to->value,'reason'=>$reason,'actor_user_id'=>$actorId,'created_at'=>now()]);
    }
}
