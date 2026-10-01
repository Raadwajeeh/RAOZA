<?php
namespace App\Domain\Payments\Providers;
use App\Domain\Commerce\Models\Order;
use App\Domain\Payments\Contracts\PaymentProvider;
use App\Domain\Payments\Data\ProviderPayment;
use App\Domain\Payments\Data\ProviderRefund;
use App\Domain\Payments\Data\ProviderRefundRequest;
use App\Domain\Payments\Models\Payment;
use App\Domain\Returns\Models\Refund;
use RuntimeException;
class DemoPaymentProvider implements PaymentProvider {
 public function name():string{return 'demo';}
 public function create(Order $order,string $redirectUrl,string $webhookUrl,string $idempotencyKey):ProviderPayment {
  $this->guard(); $id='demo_'.substr(hash('sha256',$idempotencyKey),0,16);
  return new ProviderPayment($id,'open',$order->total_amount,$order->currency,route('demo.payment.show',['providerPaymentId'=>$id]),'demo',$order->order_number,['demo'=>true]);
 }
 public function fetch(string $id):ProviderPayment {
  $this->guard(); $p=Payment::query()->where('provider','demo')->where('provider_payment_id',$id)->firstOrFail();$status=(string)($p->metadata['demo_status']??'open');
  return new ProviderPayment($id,$status,$p->amount,$p->currency,route('demo.payment.show',['providerPaymentId'=>$id]),'demo',$p->order->order_number,['demo'=>true]);
 }
 public function createRefund(ProviderRefundRequest $request):ProviderRefund {
  $this->guard();$id='demo_refund_'.substr(hash('sha256',$request->idempotencyKey),0,24);return new ProviderRefund($id,$request->paymentId,'refunded',$request->amount,$request->currency,now()->toIso8601String(),$request->idempotencyKey);
 }
 public function fetchRefund(string $providerPaymentId,string $providerRefundId):ProviderRefund {
  $this->guard();$refund=Refund::query()->with('payment')->where('provider_refund_id',$providerRefundId)->firstOrFail();if($refund->payment->provider_payment_id!==$providerPaymentId)throw new RuntimeException('Demo refund does not belong to this payment.');$status=(string)($refund->metadata['demo_refund_status']??'refunded');return new ProviderRefund($providerRefundId,$providerPaymentId,$status,$refund->amount,$refund->currency,$refund->submission_started_at?->toIso8601String(),$refund->metadata['provider_reference']??null);
 }
 public function findRefund(string $providerPaymentId,string $reference):?ProviderRefund {
  $this->guard();$refund=Refund::query()->with('payment')->whereNotNull('provider_refund_id')->get()->first(fn(Refund $refund):bool=>$refund->payment->provider_payment_id===$providerPaymentId&&($refund->metadata['provider_reference']??null)===$reference);return $refund?$this->fetchRefund($providerPaymentId,$refund->provider_refund_id):null;
 }
 private function guard():void{if(!app()->environment(['local','testing'])||!config('commerce.demo_mode'))throw new RuntimeException('Demo payments are available only in local/testing demo mode.');}
}
