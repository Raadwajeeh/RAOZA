<?php
namespace Tests\Feature\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
class CartSchemaTest extends TestCase
{
    use RefreshDatabase;
    public function test_cart_schema_exists(): void
    {
        $this->assertTrue(Schema::hasColumns('carts',['token','user_id','status','currency','expires_at']));
        $this->assertTrue(Schema::hasColumns('cart_items',['cart_id','variant_id','quantity']));
    }
}
