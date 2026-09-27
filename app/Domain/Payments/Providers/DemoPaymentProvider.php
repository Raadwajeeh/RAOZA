<?php
namespace App\Domain\Payments\Providers;
use App\Domain\Commerce\Models\Order;
use App\Domain\Payments\Contracts\PaymentProvider;
use App\Domain\Payments\Data\ProviderPayment;
use App\Domain\Payments\Models\Payment;
use RuntimeException;
class DemoPaymentProvider implements PaymentProvider {
 public function name():string{return 'demo';}
 public function create(Order $order,string $redirectUrl,string $webhookUrl):ProviderPayment {
  $this->guard(); $id='demo_'.bin2hex(random_bytes(8));
  return new ProviderPayment($id,'open',$order->total_amount,$order->currency,route('demo.payment.show',['providerPaymentId'=>$id]),'demo',$order->order_number,['demo'=>true]);
 }
 public function fetch(string $id):ProviderPayment {
  $this->guard(); $p=Payment::query()->where('provider','demo')->where('provider_payment_id',$id)->firstOrFail();$status=(string)($p->metadata['demo_status']??'open');
  return new ProviderPayment($id,$status,$p->amount,$p->currency,route('demo.payment.show',['providerPaymentId'=>$id]),'demo',$p->order->order_number,['demo'=>true]);
 }
 private function guard():void{if(!app()->environment(['local','testing'])||!config('commerce.demo_mode'))throw new RuntimeException('Demo payments are available only in local/testing demo mode.');}
}
