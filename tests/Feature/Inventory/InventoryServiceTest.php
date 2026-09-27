<?php
namespace Tests\Feature\Inventory;
use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Enums\VariantStatus;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Inventory\Enums\InventoryMovementType;
use App\Domain\Inventory\Models\Inventory;
use App\Domain\Inventory\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;
class InventoryServiceTest extends TestCase
{
    use RefreshDatabase;
    private function variant(): ProductVariant
    {
        $product = Product::create(['name'=>'Urban Tee','slug'=>'urban-tee','status'=>ProductStatus::Draft,'base_price'=>3495]);
        return ProductVariant::create(['product_id'=>$product->id,'sku'=>'RZ-TEE-BLK-M','status'=>VariantStatus::Active]);
    }
    public function test_adjustment_is_atomic_and_records_movement(): void
    {
        $variant=$this->variant(); Inventory::create(['variant_id'=>$variant->id]);
        $inventory=app(InventoryService::class)->adjust($variant, 10, InventoryMovementType::StockReceived, 'Initial stock');
        $this->assertSame(10,$inventory->quantity_on_hand);
        $this->assertDatabaseHas('inventory_movements',['variant_id'=>$variant->id,'quantity'=>10,'type'=>'stock_received']);
    }
    public function test_reservation_cannot_exceed_available_stock(): void
    {
        $variant=$this->variant(); Inventory::create(['variant_id'=>$variant->id,'quantity_on_hand'=>2]);
        $this->expectException(RuntimeException::class);
        app(InventoryService::class)->reserve($variant,3);
    }
}
