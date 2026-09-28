<?php

namespace Tests\Feature;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Models\{Inventory,InventoryMovement};
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminInventoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_records_typed_stock_movements_without_overwriting_history(): void
    {
        $owner=User::factory()->create(['role'=>AdminRole::Owner]);
        $product=Product::create(['name'=>'Stock Tee','slug'=>'stock-tee','base_price'=>2995,'status'=>'draft']);
        $variant=$product->variants()->create(['sku'=>'RZ-STOCK-M','status'=>'active','option_signature'=>'stock-m']);
        Inventory::create(['variant_id'=>$variant->id,'quantity_on_hand'=>5,'quantity_reserved'=>0]);

        $this->actingAs($owner)->patch("/admin/inventory/{$variant->id}",['delta'=>4,'type'=>'stock_received','reason'=>'Supplier receipt','note'=>'QA delivery'])->assertSessionHasNoErrors();
        $this->assertSame(9,$variant->inventory()->firstOrFail()->quantity_on_hand);
        $this->assertDatabaseHas('inventory_movements',['variant_id'=>$variant->id,'type'=>'stock_received','quantity'=>4]);

        $this->patch("/admin/inventory/{$variant->id}",['delta'=>1,'type'=>'damaged','reason'=>'Damaged item'])->assertSessionHasErrors('delta');
        $this->assertSame(1,InventoryMovement::where('variant_id',$variant->id)->count());

        $this->patch("/admin/inventory/{$variant->id}",['delta'=>-2,'type'=>'damaged','reason'=>'Damaged item'])->assertSessionHasNoErrors();
        $this->assertSame(7,$variant->inventory()->firstOrFail()->quantity_on_hand);
        $this->assertSame(2,InventoryMovement::where('variant_id',$variant->id)->count());
    }
}
