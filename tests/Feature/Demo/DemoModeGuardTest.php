<?php
namespace Tests\Feature\Demo;
use App\Domain\Commerce\Enums\PaymentStatus;
use App\Domain\Payments\Contracts\PaymentProvider;
use App\Domain\Payments\Data\ProviderRefundRequest;
use App\Domain\Payments\Providers\DemoPaymentProvider;
use App\Domain\Payments\Services\PaymentService;
use App\Domain\Returns\Enums\RefundStatus;
use App\Domain\Returns\Services\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Support\BuildsCommerceFixtures;
use Tests\TestCase;
class DemoModeGuardTest extends TestCase {
 use BuildsCommerceFixtures;
 use RefreshDatabase;
 public function test_demo_payment_route_is_hidden_when_demo_mode_is_off():void {config(['commerce.demo_mode'=>false]);$this->get('/demo/payments/not-real')->assertNotFound();}
 public function test_demo_payment_is_blocked_server_side_in_production_even_if_enabled():void {$this->app->detectEnvironment(fn()=>'production');config(['commerce.demo_mode'=>true,'payments.default'=>'demo']);$this->get('/demo/payments/not-real')->assertNotFound();$this->expectException(RuntimeException::class);app(DemoPaymentProvider::class)->fetch('not-real');}
 public function test_demo_refund_completes_deterministically_in_testing_demo_mode():void {
  config(['commerce.demo_mode'=>true,'payments.default'=>'demo']);$provider=app(DemoPaymentProvider::class);$this->app->instance(PaymentProvider::class,$provider);
  $fixture=$this->checkoutOrder([['price'=>1000,'stock'=>2]]);$payment=app(PaymentService::class)->createAttempt($fixture['order'],'https://store.test/return','https://store.test/webhook');
  $payment->update(['metadata'=>array_merge($payment->metadata??[],['demo_status'=>'paid'])]);app(PaymentService::class)->sync($payment->fresh(),'demo_complete');
  $refund=app(RefundService::class)->request($fixture['order']->fresh(),400,idempotencyKey:'demo-refund');$completed=app(RefundService::class)->submit($refund);
  $this->assertSame(RefundStatus::Succeeded,$completed->status);$this->assertStringStartsWith('demo_refund_',$completed->provider_refund_id);$this->assertSame(PaymentStatus::PartiallyRefunded,$fixture['order']->fresh()->payment_status);
 }
 public function test_demo_refund_provider_is_blocked_in_production_even_if_enabled():void {
  $this->app->detectEnvironment(fn()=>'production');config(['commerce.demo_mode'=>true]);
  $this->expectException(RuntimeException::class);app(DemoPaymentProvider::class)->createRefund(new ProviderRefundRequest('demo_payment',100,'EUR','key','Test refund',1));
 }
}
