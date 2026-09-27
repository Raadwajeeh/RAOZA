<?php
namespace Tests\Feature\Payments;
use Tests\TestCase;
class PaymentRoutesTest extends TestCase { public function test_payment_routes_are_registered():void { $this->assertNotNull(app('router')->getRoutes()->getByName('payments.start')); $this->assertNotNull(app('router')->getRoutes()->getByName('payments.return')); $this->assertNotNull(app('router')->getRoutes()->getByName('payments.webhook')); } }
