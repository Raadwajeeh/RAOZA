<?php
namespace Tests\Feature\Cart;
use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Enums\VariantStatus;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Commerce\Enums\CartStatus;
use App\Domain\Commerce\Models\Cart;
use App\Domain\Commerce\Services\CartService;
use App\Domain\Inventory\Models\Inventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
class CartServiceTest extends TestCase
{
    use RefreshDatabase;
    public function test_server_uses_catalog_price_and_enforces_available_stock(): void
    {
        $product=Product::create(['name'=>'Test Tee','slug'=>'test-tee','status'=>ProductStatus::Active,'base_price'=>3495,'published_at'=>now()]);
        $variant=ProductVariant::create(['product_id'=>$product->id,'sku'=>'RZ-TEST-S','status'=>VariantStatus::Active,'price_override'=>3995]);
        Inventory::create(['variant_id'=>$variant->id,'quantity_on_hand'=>3,'quantity_reserved'=>1]);
        $cart=Cart::create(['token'=>(string)Str::uuid(),'status'=>CartStatus::Active,'currency'=>'EUR']);
        $service=app(CartService::class);
        $service->add($cart,$variant->id,2);
        $summary=$service->summary($cart->fresh());
        $this->assertSame(3995,$summary['items'][0]['unitPrice']);
        $this->assertSame(7990,$summary['subtotal']);
        $this->expectException(ValidationException::class);
        $service->add($cart,$variant->id,1);
    }

    public function test_converted_cart_cannot_be_mutated(): void
    {
        $product=Product::create(['name'=>'Locked Tee','slug'=>'locked-tee','status'=>ProductStatus::Active,'base_price'=>2000,'published_at'=>now()]);
        $variant=ProductVariant::create(['product_id'=>$product->id,'sku'=>'RZ-LOCKED','status'=>VariantStatus::Active]);
        Inventory::create(['variant_id'=>$variant->id,'quantity_on_hand'=>3,'quantity_reserved'=>0]);
        $cart=Cart::create(['token'=>(string)Str::uuid(),'status'=>CartStatus::Active,'currency'=>'EUR']);
        $item=app(CartService::class)->add($cart,$variant->id,1);
        $cart->update(['status'=>CartStatus::Converted]);

        $this->expectException(ValidationException::class);
        app(CartService::class)->update($cart->fresh(),$item,2);
    }
}
