<?php
namespace Tests\Feature\Inventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
class InventorySchemaTest extends TestCase
{
    use RefreshDatabase;
    public function test_media_and_inventory_tables_exist(): void
    {
        $this->assertTrue(Schema::hasColumns('product_images', ['product_id','variant_id','path','alt_text','position']));
        $this->assertTrue(Schema::hasColumns('inventories', ['variant_id','quantity_on_hand','quantity_reserved']));
        $this->assertTrue(Schema::hasColumns('inventory_movements', ['variant_id','type','quantity','reference_type','reference_id']));
    }
}
