<?php
namespace App\Domain\Commerce\Services;
use App\Domain\Commerce\Enums\FulfillmentStatus;
use App\Domain\Commerce\Enums\OrderStatus;
use App\Domain\Commerce\Enums\PaymentStatus;
use App\Domain\Commerce\Models\Order;
use App\Domain\Inventory\Models\Inventory;
use Illuminate\Support\Facades\DB;
use RuntimeException;
class OrderCancellationService {
 public function cancel(Order $order,?int $actorId=null,string $reason='admin_cancelled'):Order {return DB::transaction(function()use($order,$actorId,$reason){$o=Order::whereKey($order->id)->lockForUpdate()->with('items')->firstOrFail();if($o->order_status===OrderStatus::Cancelled)return $o;if($o->order_status===OrderStatus::Confirmed||$o->fulfillment_status===FulfillmentStatus::Fulfilled||$o->payment_status->isSettled())throw new RuntimeException('Paid or confirmed orders require an explicit refund/return decision and cannot use unpaid cancellation.');$from=$o->order_status->value;$fromFulfillment=$o->fulfillment_status->value;foreach($o->items->sortBy('variant_id') as $item){if(!$item->variant_id)continue;$inv=Inventory::where('variant_id',$item->variant_id)->lockForUpdate()->first();if(!$inv)continue;$release=min($item->quantity,$inv->quantity_reserved);if($release){$inv->quantity_reserved-=$release;$inv->assertConsistent();$inv->save();}}$o->update(['order_status'=>OrderStatus::Cancelled,'payment_status'=>PaymentStatus::Failed,'fulfillment_status'=>FulfillmentStatus::Cancelled,'cancelled_at'=>now()]);$o->statusHistory()->create(['domain'=>'order','from_status'=>$from,'to_status'=>OrderStatus::Cancelled->value,'reason'=>$reason,'actor_user_id'=>$actorId,'created_at'=>now()]);if($fromFulfillment!==FulfillmentStatus::Cancelled->value)$o->statusHistory()->create(['domain'=>'fulfillment','from_status'=>$fromFulfillment,'to_status'=>FulfillmentStatus::Cancelled->value,'reason'=>$reason,'actor_user_id'=>$actorId,'created_at'=>now()]);return $o->refresh();},3);}
}
