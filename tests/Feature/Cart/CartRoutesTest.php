<?php
namespace Tests\Feature\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class CartRoutesTest extends TestCase
{
    use RefreshDatabase;
    public function test_cart_page_is_available_and_creates_guest_cart(): void
    {
        $this->get('/cart')->assertOk()->assertCookie('raoza_cart');
        $this->assertDatabaseCount('carts',1);
    }
}
