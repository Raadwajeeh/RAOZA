<?php
namespace App\Domain\Returns\Services;
use App\Domain\Commerce\Enums\PaymentStatus;
use App\Domain\Commerce\Models\Order;
use App\Domain\Payments\Enums\PaymentAttemptStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Returns\Enums\RefundStatus;
use App\Domain\Returns\Models\Refund;
use App\Domain\Returns\Models\ReturnRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
class RefundService {
 public function request(Order $order,int $amount,?ReturnRequest $return=null,?string $reason=null,?string $idempotencyKey=null):Refund {
  if($amount<=0)throw new RuntimeException('Refund amount must be positive.');
  if(!in_array($order->payment_status,[PaymentStatus::Paid,PaymentStatus::PartiallyRefunded],true))throw new RuntimeException('Only paid or partially refunded orders can be refunded.');
  return DB::transaction(function()use($order,$amount,$return,$reason,$idempotencyKey){$o=Order::whereKey($order->id)->lockForUpdate()->firstOrFail();$key=$idempotencyKey?:Str::uuid()->toString();if($return && !in_array($return->status,[\App\Domain\Returns\Enums\ReturnStatus::Inspected,\App\Domain\Returns\Enums\ReturnStatus::Completed],true))throw new RuntimeException('A linked return must be inspected before a refund is requested.');if($existing=Refund::where('idempotency_key',$key)->first())return $existing;$payment=Payment::where('order_id',$o->id)->where('status',PaymentAttemptStatus::Paid->value)->orderByDesc('paid_at')->lockForUpdate()->firstOrFail();$succeeded=Refund::where('order_id',$o->id)->where('status',RefundStatus::Succeeded->value)->sum('amount');$pending=Refund::where('order_id',$o->id)->whereIn('status',[RefundStatus::Requested->value,RefundStatus::Processing->value])->sum('amount');$available=$payment->amount-$succeeded-$pending;if($amount>$available)throw new RuntimeException('Refund exceeds the remaining refundable amount.');return Refund::create(['payment_id'=>$payment->id,'order_id'=>$o->id,'return_id'=>$return?->id,'amount'=>$amount,'currency'=>$o->currency,'reason'=>$reason,'status'=>RefundStatus::Requested,'idempotency_key'=>$key,'requested_at'=>now()]);},3);
 }
 public function markSucceeded(Refund $refund,?string $providerRefundId=null):Refund {return DB::transaction(function()use($refund,$providerRefundId){$r=Refund::whereKey($refund->id)->lockForUpdate()->firstOrFail();if($r->status===RefundStatus::Succeeded)return $r;if(!in_array($r->status,[RefundStatus::Requested,RefundStatus::Processing],true))throw new RuntimeException('Only requested or processing refunds can succeed.');$r->update(['status'=>RefundStatus::Succeeded,'provider_refund_id'=>$providerRefundId,'processed_at'=>now()]);$o=Order::whereKey($r->order_id)->lockForUpdate()->firstOrFail();$total=Refund::where('order_id',$o->id)->where('status',RefundStatus::Succeeded->value)->sum('amount');$o->update(['payment_status'=>$total>=$o->total_amount?PaymentStatus::Refunded:PaymentStatus::PartiallyRefunded]);return $r->refresh();},3);}
 public function markFailed(Refund $refund):Refund {return DB::transaction(function()use($refund){$r=Refund::whereKey($refund->id)->lockForUpdate()->firstOrFail();if(!in_array($r->status,[RefundStatus::Requested,RefundStatus::Processing],true))throw new RuntimeException('Only requested or processing refunds can fail.');$r->update(['status'=>RefundStatus::Failed,'processed_at'=>now()]);return $r->refresh();},3);}
}
