<?php
namespace Tests\Feature\Commerce;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class CheckoutRoutesTest extends TestCase
{
    use RefreshDatabase;
    public function test_empty_cart_checkout_redirects_to_cart(): void
    {
        $this->get('/checkout')->assertRedirect('/cart');
    }
    public function test_checkout_rejects_missing_customer_and_address_data(): void
    {
        $this->post('/checkout',[])->assertSessionHasErrors(['email','billing_same_as_shipping','shipping_first_name','shipping_last_name','shipping_street','shipping_house_number','shipping_postal_code','shipping_city','shipping_country_code']);
    }
}
