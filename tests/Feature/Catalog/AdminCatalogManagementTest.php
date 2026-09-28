<?php
namespace Tests\Feature\Catalog;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Catalog\Models\{Category,Collection,Product,ProductOption};
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminCatalogManagementTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User { return User::factory()->create(['role'=>AdminRole::Owner,'is_admin'=>false]); }

    public function test_owner_can_build_a_product_variant_with_opening_inventory(): void
    {
        $this->actingAs($this->owner());
        $product=Product::create(['name'=>'Test Tee','slug'=>'test-tee','base_price'=>2995,'status'=>'draft']);
        $size=$product->options()->create(['name'=>'Size','position'=>1]);
        $small=$size->values()->create(['value'=>'S','position'=>1]);

        $this->post("/admin/products/{$product->id}/variants",[
            'sku'=>'RZ-TEST-S','price_override'=>null,'status'=>'active','option_value_ids'=>[$small->id],'opening_stock'=>7,
        ])->assertSessionHasNoErrors();

        $variant=$product->variants()->firstOrFail();
        $this->assertSame(7,$variant->inventory()->firstOrFail()->quantity_on_hand);
        $this->assertTrue($variant->optionValues()->whereKey($small->id)->exists());
    }

    public function test_variant_requires_one_value_for_each_product_option(): void
    {
        $this->actingAs($this->owner());
        $product=Product::create(['name'=>'Test Hoodie','slug'=>'test-hoodie','base_price'=>5995,'status'=>'draft']);
        $size=$product->options()->create(['name'=>'Size','position'=>1]); $small=$size->values()->create(['value'=>'S','position'=>1]);
        ProductOption::create(['product_id'=>$product->id,'name'=>'Color','position'=>2]);

        $this->post("/admin/products/{$product->id}/variants",[
            'sku'=>'RZ-HOOD-S','status'=>'active','option_value_ids'=>[$small->id],'opening_stock'=>1,
        ])->assertSessionHasErrors('option_value_ids');
        $this->assertSame(0,$product->variants()->count());
    }

    public function test_owner_can_upload_product_media_to_public_storage(): void
    {
        Storage::fake('public');
        $this->actingAs($this->owner());
        $product=Product::create(['name'=>'Media Tee','slug'=>'media-tee','base_price'=>2995,'status'=>'draft']);

        $this->post("/admin/products/{$product->id}/images",[
            'images'=>[UploadedFile::fake()->image('front.jpg',1200,1500)],'alt_text'=>'Media Tee front',
        ])->assertSessionHasNoErrors();

        $image=$product->images()->firstOrFail();
        Storage::disk('public')->assertExists($image->path);
        $this->assertSame('Media Tee front',$image->alt_text);
        $this->assertSame(1200,$image->width);
    }

    public function test_variant_generator_creates_each_combination_once_with_inventory_movements(): void
    {
        $this->actingAs($this->owner());
        $product=Product::create(['name'=>'Generated Hoodie','slug'=>'generated-hoodie','base_price'=>6995,'status'=>'draft']);
        foreach(['Size'=>['S','M'],'Color'=>['Cream','Black']] as $name=>$values){$option=$product->options()->create(['name'=>$name,'position'=>$product->options()->count()+1]);foreach($values as $position=>$value)$option->values()->create(['value'=>$value,'position'=>$position+1]);}

        $payload=['sku_prefix'=>'RZ-GEN','opening_stock'=>3,'status'=>'active'];
        $this->post("/admin/products/{$product->id}/variants/generate",$payload)->assertSessionHasNoErrors();
        $this->post("/admin/products/{$product->id}/variants/generate",$payload)->assertSessionHasNoErrors();

        $this->assertSame(4,$product->variants()->count());
        $this->assertSame(4,$product->variants()->whereHas('inventory',fn($query)=>$query->where('quantity_on_hand',3))->count());
        $this->assertSame(4,\App\Domain\Inventory\Models\InventoryMovement::where('reference_type','admin_opening_stock')->count());
    }

    public function test_owner_can_create_and_edit_taxonomies_with_product_assignments(): void
    {
        $this->actingAs($this->owner());
        $product=Product::create(['name'=>'Assigned Tee','slug'=>'assigned-tee','base_price'=>3495,'status'=>'draft']);

        $this->post('/admin/categories',['name'=>'Editorial Tees','slug'=>'editorial-tees','description'=>'Category copy','status'=>'active','position'=>4,'indexable'=>true,'product_ids'=>[$product->id]])->assertSessionHasNoErrors();
        $category=Category::where('slug','editorial-tees')->firstOrFail();
        $this->assertTrue($category->products()->whereKey($product->id)->exists());
        $this->patch("/admin/categories/{$category->id}",['name'=>'Editorial T-Shirts','slug'=>'editorial-tees','description'=>'Updated category copy','status'=>'active','position'=>3,'indexable'=>true,'product_ids'=>[$product->id]])->assertSessionHasNoErrors();
        $this->assertSame('Editorial T-Shirts',$category->fresh()->name);

        $this->post('/admin/collections',['name'=>'Studio Edit','slug'=>'studio-edit','description'=>'Editorial collection','status'=>'active','indexable'=>true,'product_ids'=>[$product->id]])->assertSessionHasNoErrors();
        $collection=Collection::where('slug','studio-edit')->firstOrFail();
        $this->assertTrue($collection->products()->whereKey($product->id)->exists());
        $this->patch("/admin/collections/{$collection->id}",['name'=>'Studio Essentials','slug'=>'studio-edit','description'=>'Updated editorial copy','status'=>'active','indexable'=>true,'product_ids'=>[$product->id]])->assertSessionHasNoErrors();
        $this->assertSame('Studio Essentials',$collection->fresh()->name);
    }

    public function test_only_unused_option_values_can_be_removed(): void
    {
        $this->actingAs($this->owner());
        $product=Product::create(['name'=>'Option Tee','slug'=>'option-tee','base_price'=>3495,'status'=>'draft']);
        $option=$product->options()->create(['name'=>'Size','position'=>1]);
        $used=$option->values()->create(['value'=>'M','position'=>1]);
        $unused=$option->values()->create(['value'=>'L','position'=>2]);
        $variant=$product->variants()->create(['sku'=>'RZ-OPTION-M','status'=>'active','option_signature'=>(string)$used->id]);
        $variant->optionValues()->attach($used);

        $this->delete("/admin/products/{$product->id}/options/{$option->id}/values/{$unused->id}")->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('product_option_values',['id'=>$unused->id]);
        $this->delete("/admin/products/{$product->id}/options/{$option->id}/values/{$used->id}")->assertSessionHasErrors('option');
        $this->assertDatabaseHas('product_option_values',['id'=>$used->id]);
    }
}
