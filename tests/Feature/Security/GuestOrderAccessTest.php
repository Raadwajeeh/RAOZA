<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCommerceFixtures;
use Tests\TestCase;

class GuestOrderAccessTest extends TestCase
{
    use BuildsCommerceFixtures;
    use RefreshDatabase;

    public function test_guest_session_cannot_access_or_pay_another_order(): void
    {
        $owned = $this->checkoutOrder();
        $other = $this->checkoutOrder();
        $session = ['last_order_number' => $owned['order']->order_number];

        $this->withSession($session)->get(route('checkout.confirmation', $other['order']->order_number))->assertNotFound();
        $this->withSession($session)->post(route('payments.start', $other['order']->order_number))->assertNotFound();
        $this->withSession($session)->get(route('payments.return', $other['order']->order_number))->assertNotFound();
        $this->get('/order-confirmation/RZ-NOT-A-REAL-ORDER')->assertNotFound();
    }

    public function test_unknown_webhook_identifier_returns_no_order_information(): void
    {
        $this->post(route('payments.webhook'), ['id' => 'tr_unknown'])->assertOk()->assertContent('');
    }
}
