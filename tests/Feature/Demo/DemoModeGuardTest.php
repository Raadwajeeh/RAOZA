<?php
namespace Tests\Feature\Demo;
use Tests\TestCase;
class DemoModeGuardTest extends TestCase {
 public function test_demo_payment_route_is_hidden_when_demo_mode_is_off():void {config(['commerce.demo_mode'=>false]);$this->get('/demo/payments/not-real')->assertNotFound();}
}
