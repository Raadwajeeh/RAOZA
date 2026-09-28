<?php
namespace Tests\Feature\Commerce;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
class OrderSchemaTest extends TestCase
{
    use RefreshDatabase;
    public function test_order_schema_contains_financial_snapshots_and_status_boundaries(): void
    {
        $this->assertTrue(Schema::hasColumns('orders',['order_number','source_cart_id','customer_email','currency','subtotal_amount','discount_amount','shipping_amount','shipping_tax_amount','tax_amount','total_amount','order_status','payment_status','fulfillment_status','placed_at']));
        $this->assertTrue(Schema::hasColumns('order_items',['product_id','variant_id','product_name','variant_options','sku','unit_price','quantity','subtotal_amount','total_amount','product_snapshot']));
        $this->assertTrue(Schema::hasColumns('order_addresses',['order_id','type','first_name','last_name','street','house_number','postal_code','city','country_code']));
        $this->assertTrue(Schema::hasColumns('order_status_history',['order_id','domain','from_status','to_status','reason','actor_user_id','created_at']));
    }
}
