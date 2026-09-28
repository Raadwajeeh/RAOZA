<?php
namespace Tests\Feature\Demo;
use App\Domain\Payments\Providers\DemoPaymentProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;
class DemoModeGuardTest extends TestCase {
 use RefreshDatabase;
 public function test_demo_payment_route_is_hidden_when_demo_mode_is_off():void {config(['commerce.demo_mode'=>false]);$this->get('/demo/payments/not-real')->assertNotFound();}
 public function test_demo_payment_is_blocked_server_side_in_production_even_if_enabled():void {$this->app->detectEnvironment(fn()=>'production');config(['commerce.demo_mode'=>true,'payments.default'=>'demo']);$this->get('/demo/payments/not-real')->assertNotFound();$this->expectException(RuntimeException::class);app(DemoPaymentProvider::class)->fetch('not-real');}
}
