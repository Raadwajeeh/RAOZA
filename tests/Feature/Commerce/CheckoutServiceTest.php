<?php
namespace Tests\Feature\Commerce;
use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Enums\VariantStatus;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Commerce\Enums\CartStatus;
use App\Domain\Commerce\Models\Cart;
use App\Domain\Commerce\Services\CheckoutService;
use App\Domain\Inventory\Models\Inventory;
use App\Domain\Fulfillment\Models\ShippingMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
class CheckoutServiceTest extends TestCase
{
    use RefreshDatabase;
    public function test_checkout_snapshots_money_and_reserves_stock_once(): void
    {
        $product=Product::create(['name'=>'Editorial Tee','slug'=>'editorial-tee','status'=>ProductStatus::Active,'base_price'=>3495,'published_at'=>now()->subMinute()]);
        $variant=ProductVariant::create(['product_id'=>$product->id,'sku'=>'RZ-TEE-M','status'=>VariantStatus::Active,'option_signature'=>'m']);
        $inventory=Inventory::create(['variant_id'=>$variant->id,'quantity_on_hand'=>5,'quantity_reserved'=>0]);
        $cart=Cart::create(['token'=>(string)Str::uuid(),'status'=>CartStatus::Active,'currency'=>'EUR','expires_at'=>now()->addDay()]);
        $cart->items()->create(['variant_id'=>$variant->id,'quantity'=>2]);
        $shipping=ShippingMethod::create(['name'=>'Test delivery','code'=>'test-delivery','price'=>0,'currency'=>'EUR','active'=>true]);
        $data=['email'=>'Buyer@Example.com','phone'=>null,'shipping_method_id'=>$shipping->id,'billing_same_as_shipping'=>true,'shipping_first_name'=>'Raad','shipping_last_name'=>'Test','shipping_company'=>null,'shipping_street'=>'Teststraat','shipping_house_number'=>'10','shipping_addition'=>null,'shipping_postal_code'=>'9401 aa','shipping_city'=>'Assen','shipping_country_code'=>'NL'];
        $service=app(CheckoutService::class); $order=$service->createOrder($cart,$data); $again=$service->createOrder($cart->fresh(),$data);
        $this->assertSame($order->id,$again->id); $this->assertSame(6990,$order->total_amount); $this->assertSame('buyer@example.com',$order->customer_email);
        $this->assertSame(2,$inventory->fresh()->quantity_reserved); $this->assertSame(CartStatus::Converted,$cart->fresh()->status);
        $this->assertDatabaseHas('order_items',['order_id'=>$order->id,'product_name'=>'Editorial Tee','sku'=>'RZ-TEE-M','unit_price'=>3495,'quantity'=>2,'total_amount'=>6990]);
        $this->assertDatabaseHas('order_addresses',['order_id'=>$order->id,'type'=>'shipping','postal_code'=>'9401 AA']);
        $this->assertDatabaseHas('order_addresses',['order_id'=>$order->id,'type'=>'billing','postal_code'=>'9401 AA']);
    }
}
