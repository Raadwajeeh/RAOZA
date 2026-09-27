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
 public function cancel(Order $order,?int $actorId=null,string $reason='admin_cancelled'):Order {return DB::transaction(function()use($order,$actorId,$reason){$o=Order::whereKey($order->id)->lockForUpdate()->with('items')->firstOrFail();if($o->fulfillment_status===FulfillmentStatus::Fulfilled)throw new RuntimeException('Shipped/fulfilled orders must use the return flow.');if($o->order_status===OrderStatus::Cancelled)return $o;if($o->payment_status===PaymentStatus::Paid||$o->payment_status===PaymentStatus::PartiallyRefunded)throw new RuntimeException('Paid orders require a refund decision before cancellation.');foreach($o->items->sortBy('variant_id') as $item){if(!$item->variant_id)continue;$inv=Inventory::where('variant_id',$item->variant_id)->lockForUpdate()->first();if(!$inv)continue;$release=min($item->quantity,$inv->quantity_reserved);if($release){$inv->quantity_reserved-=$release;$inv->assertConsistent();$inv->save();}}$from=$o->order_status->value;$o->update(['order_status'=>OrderStatus::Cancelled,'fulfillment_status'=>FulfillmentStatus::Cancelled,'cancelled_at'=>now()]);$o->statusHistory()->create(['domain'=>'order','from_status'=>$from,'to_status'=>OrderStatus::Cancelled->value,'reason'=>$reason,'actor_id'=>$actorId,'created_at'=>now()]);return $o->refresh();},3);}
}
