<?php
namespace App\Domain\Payments\Services;
use App\Domain\Commerce\Enums\OrderStatus;
use App\Domain\Commerce\Enums\FulfillmentStatus;
use App\Domain\Commerce\Enums\PaymentStatus;
use App\Domain\Commerce\Models\Order;
use App\Domain\Inventory\Enums\InventoryMovementType;
use App\Domain\Inventory\Models\Inventory;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\Payments\Contracts\PaymentProvider;
use App\Domain\Payments\Data\ProviderPayment;
use App\Domain\Payments\Enums\PaymentAttemptStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
class PaymentService {
 public function __construct(private PaymentProvider $provider) {}
 public function createAttempt(Order $order,string $redirectUrl,string $webhookUrl):Payment {
  if($order->payment_status===PaymentStatus::Paid) throw new RuntimeException('Order is already paid.');
  $this->ensureReservation($order);
  $payment=Payment::create(['order_id'=>$order->id,'provider'=>$this->provider->name(),'currency'=>$order->currency,'amount'=>$order->total_amount,'status'=>PaymentAttemptStatus::Created,'metadata'=>['order_number'=>$order->order_number]]);
  try { $remote=$this->provider->create($order,$redirectUrl,$webhookUrl); $this->assertMatches($order,$remote); $payment->update(['provider_payment_id'=>$remote->id,'method'=>$remote->method,'status'=>$this->mapStatus($remote->status),'provider_created_at'=>now(),'metadata'=>['order_number'=>$order->order_number,'checkout_url'=>$remote->checkoutUrl]]); $order->update(['payment_status'=>PaymentStatus::Pending]); return $payment->refresh(); }
  catch(\Throwable $e){ $payment->update(['status'=>PaymentAttemptStatus::Failed,'failed_at'=>now()]); throw $e; }
 }
 public function sync(Payment $payment,string $eventType='provider_sync'):Payment {
  if(!$payment->provider_payment_id) throw new RuntimeException('Payment has no provider id.');
  $remote=$this->provider->fetch($payment->provider_payment_id); $this->assertMatches($payment->order,$remote);
  return $this->apply($payment,$remote,$eventType);
 }
 private function apply(Payment $payment,ProviderPayment $remote,string $eventType):Payment {
  return DB::transaction(function()use($payment,$remote,$eventType){
   $locked=Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail(); $order=Order::whereKey($locked->order_id)->lockForUpdate()->firstOrFail();
   $key=hash('sha256',implode('|',[$locked->provider,$remote->id,$remote->status,$eventType]));
   if(PaymentEvent::where('event_key',$key)->exists()) return $locked->refresh();
   $new=$this->mapStatus($remote->status); $changes=['status'=>$new,'method'=>$remote->method ?: $locked->method];
   if($new===PaymentAttemptStatus::Paid)$changes['paid_at']=$locked->paid_at?:now();
   if($new===PaymentAttemptStatus::Failed)$changes['failed_at']=$locked->failed_at?:now();
   if($new===PaymentAttemptStatus::Cancelled)$changes['cancelled_at']=$locked->cancelled_at?:now();
   if($new===PaymentAttemptStatus::Expired)$changes['expired_at']=$locked->expired_at?:now();
   $locked->update($changes);
   if($new===PaymentAttemptStatus::Paid && $order->payment_status!==PaymentStatus::Paid) $this->commitPaidOrder($order);
   elseif(in_array($new,[PaymentAttemptStatus::Failed,PaymentAttemptStatus::Cancelled,PaymentAttemptStatus::Expired],true) && $order->payment_status!==PaymentStatus::Paid && !Payment::where('order_id',$order->id)->where('id','>',$locked->id)->exists()) $this->releaseOrder($order,$new->value);
   elseif(in_array($new,[PaymentAttemptStatus::Open,PaymentAttemptStatus::Pending],true) && $order->payment_status!==PaymentStatus::Paid) $order->update(['payment_status'=>PaymentStatus::Pending]);
   PaymentEvent::create(['payment_id'=>$locked->id,'event_key'=>$key,'event_type'=>$eventType,'provider_status'=>$remote->status,'payload'=>['provider_payment_id'=>$remote->id,'status'=>$remote->status],'processed_at'=>now(),'created_at'=>now()]);
   return $locked->refresh();
  },3);
 }
 private function commitPaidOrder(Order $order):void {
  $order->loadMissing('items'); foreach($order->items->sortBy('variant_id') as $item){ if(!$item->variant_id)throw new RuntimeException('Cannot commit stock for an order item without its variant.'); $inv=Inventory::where('variant_id',$item->variant_id)->lockForUpdate()->firstOrFail(); if($inv->quantity_reserved<$item->quantity||$inv->quantity_on_hand<$item->quantity)throw new RuntimeException('Reserved inventory is inconsistent.'); $inv->quantity_reserved-=$item->quantity; $inv->quantity_on_hand-=$item->quantity; $inv->assertConsistent(); $inv->save(); InventoryMovement::create(['variant_id'=>$item->variant_id,'type'=>InventoryMovementType::Order,'quantity'=>-$item->quantity,'reference_type'=>'order','reference_id'=>(string)$order->id,'note'=>'Paid order '.$order->order_number,'created_at'=>now()]); }
  $from=$order->order_status->value; $previousFulfillment=$order->fulfillment_status->value; $order->update(['payment_status'=>PaymentStatus::Paid,'order_status'=>OrderStatus::Confirmed,'fulfillment_status'=>FulfillmentStatus::Processing,'paid_at'=>$order->paid_at?:now()]); $order->statusHistory()->create(['domain'=>'order','from_status'=>$from,'to_status'=>OrderStatus::Confirmed->value,'reason'=>'payment_verified','created_at'=>now()]); $order->statusHistory()->create(['domain'=>'fulfillment','from_status'=>$previousFulfillment,'to_status'=>FulfillmentStatus::Processing->value,'reason'=>'payment_verified','created_at'=>now()]);
 }
 private function releaseOrder(Order $order,string $reason):void {
  if($order->order_status===OrderStatus::Cancelled)return; $order->loadMissing('items'); foreach($order->items->sortBy('variant_id') as $item){if(!$item->variant_id)continue;$inv=Inventory::where('variant_id',$item->variant_id)->lockForUpdate()->first();if(!$inv)continue;$release=min($item->quantity,$inv->quantity_reserved);if($release>0){$inv->quantity_reserved-=$release;$inv->assertConsistent();$inv->save();}}
  $from=$order->order_status->value; $order->update(['payment_status'=>PaymentStatus::Failed,'order_status'=>OrderStatus::Cancelled,'cancelled_at'=>now()]); $order->statusHistory()->create(['domain'=>'order','from_status'=>$from,'to_status'=>OrderStatus::Cancelled->value,'reason'=>'payment_'.$reason,'created_at'=>now()]);
 }

 private function ensureReservation(Order $order):void {
  DB::transaction(function()use($order){
   $locked=Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
   if($locked->payment_status===PaymentStatus::Paid)return;
   $needsReservation=$locked->order_status===OrderStatus::Cancelled;
   $locked->loadMissing('items');
   if($needsReservation){
    foreach($locked->items->sortBy('variant_id') as $item){
     if(!$item->variant_id)throw new RuntimeException('Order item no longer has a variant.');
     $inv=Inventory::where('variant_id',$item->variant_id)->lockForUpdate()->firstOrFail();
     if($inv->availableQuantity()<$item->quantity)throw new RuntimeException('Stock is no longer available for this payment retry.');
     $inv->quantity_reserved+=$item->quantity;
     $inv->assertConsistent();
     $inv->save();
    }
   }
   $locked->update(['order_status'=>OrderStatus::PendingPayment,'payment_status'=>PaymentStatus::Pending,'cancelled_at'=>null]);
  },3);
 }
 private function assertMatches(Order $order,ProviderPayment $remote):void { if($remote->amount!==$order->total_amount||strtoupper($remote->currency)!==strtoupper($order->currency)||($remote->orderNumber!==null&&$remote->orderNumber!==$order->order_number)) throw new RuntimeException('Provider payment does not match the order.'); }
 private function mapStatus(string $status):PaymentAttemptStatus{return match(strtolower($status)){'paid'=>PaymentAttemptStatus::Paid,'failed'=>PaymentAttemptStatus::Failed,'expired'=>PaymentAttemptStatus::Expired,'canceled','cancelled'=>PaymentAttemptStatus::Cancelled,'pending'=>PaymentAttemptStatus::Pending,default=>PaymentAttemptStatus::Open};}
}
