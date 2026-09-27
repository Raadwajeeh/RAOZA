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
  return DB::transaction(function()use($order,$amount,$return,$reason,$idempotencyKey){$o=Order::whereKey($order->id)->lockForUpdate()->firstOrFail();$key=$idempotencyKey?:Str::uuid()->toString();if($existing=Refund::where('idempotency_key',$key)->first())return $existing;$payment=Payment::where('order_id',$o->id)->where('status',PaymentAttemptStatus::Paid->value)->orderByDesc('paid_at')->lockForUpdate()->firstOrFail();$succeeded=Refund::where('order_id',$o->id)->where('status',RefundStatus::Succeeded->value)->sum('amount');$pending=Refund::where('order_id',$o->id)->whereIn('status',[RefundStatus::Requested->value,RefundStatus::Processing->value])->sum('amount');$available=$payment->amount-$succeeded-$pending;if($amount>$available)throw new RuntimeException('Refund exceeds the remaining refundable amount.');return Refund::create(['payment_id'=>$payment->id,'order_id'=>$o->id,'return_id'=>$return?->id,'amount'=>$amount,'currency'=>$o->currency,'reason'=>$reason,'status'=>RefundStatus::Requested,'idempotency_key'=>$key,'requested_at'=>now()]);},3);
 }
 public function markSucceeded(Refund $refund,?string $providerRefundId=null):Refund {return DB::transaction(function()use($refund,$providerRefundId){$r=Refund::whereKey($refund->id)->lockForUpdate()->firstOrFail();if($r->status===RefundStatus::Succeeded)return $r;$r->update(['status'=>RefundStatus::Succeeded,'provider_refund_id'=>$providerRefundId,'processed_at'=>now()]);$o=Order::whereKey($r->order_id)->lockForUpdate()->firstOrFail();$total=Refund::where('order_id',$o->id)->where('status',RefundStatus::Succeeded->value)->sum('amount');$o->update(['payment_status'=>$total>=$o->total_amount?PaymentStatus::Refunded:PaymentStatus::PartiallyRefunded]);return $r->refresh();},3);}
 public function markFailed(Refund $refund):Refund {$refund->update(['status'=>RefundStatus::Failed,'processed_at'=>now()]);return $refund->refresh();}
}
