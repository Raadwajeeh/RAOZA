<?php
namespace Tests\Feature\Storefront;
use App\Domain\Catalog\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class StorefrontRoutesTest extends TestCase
{
    use RefreshDatabase;
    public function test_shop_is_public(): void { $this->get('/shop')->assertOk(); }
    public function test_draft_product_is_not_publicly_visible(): void { $product=Product::create(['name'=>'Draft','slug'=>'draft','status'=>'draft','base_price'=>3495]); $this->get('/products/'.$product->slug)->assertNotFound(); }
    public function test_active_product_is_publicly_visible(): void { $product=Product::create(['name'=>'Urban Tee','slug'=>'urban-tee','status'=>'active','base_price'=>3495,'published_at'=>now()]); $this->get('/products/'.$product->slug)->assertOk(); }
}
