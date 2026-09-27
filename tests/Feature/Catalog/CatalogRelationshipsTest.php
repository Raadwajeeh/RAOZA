<?php
namespace Tests\Feature\Catalog;
use App\Domain\Catalog\Models\Collection;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductOption;
use App\Domain\Catalog\Models\ProductOptionValue;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Catalog\VariantSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class CatalogRelationshipsTest extends TestCase
{
    use RefreshDatabase;
    public function test_product_can_have_dynamic_options_variants_and_collections(): void
    {
        $product = Product::create(['name'=>'RAOZA Urban Tee','slug'=>'urban-tee','base_price'=>3495]);
        $size = ProductOption::create(['product_id'=>$product->id,'name'=>'Size']);
        $medium = ProductOptionValue::create(['product_option_id'=>$size->id,'value'=>'M']);
        $color = ProductOption::create(['product_id'=>$product->id,'name'=>'Color']);
        $black = ProductOptionValue::create(['product_option_id'=>$color->id,'value'=>'Black','metadata'=>['hex'=>'#111111']]);
        $variant = ProductVariant::create(['product_id'=>$product->id,'sku'=>'RZ-UT-BLK-M','option_signature'=>VariantSignature::fromOptionValueIds([$medium->id,$black->id])]);
        $variant->optionValues()->attach([$medium->id,$black->id]);
        $collection = Collection::create(['name'=>'Drop 01','slug'=>'drop-01']);
        $collection->products()->attach($product->id, ['position'=>1]);
        $this->assertSame(3495, $variant->fresh()->effectivePrice());
        $this->assertCount(2, $variant->optionValues);
        $this->assertTrue($product->fresh()->collections->contains($collection));
    }
}
