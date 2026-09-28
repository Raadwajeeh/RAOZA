<?php

namespace Tests\Feature\Demo;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Fulfillment\Models\ShippingMethod;
use App\Domain\Inventory\Models\Inventory;
use App\Domain\Marketing\Models\Discount;
use App\Models\User;
use Database\Seeders\LocalQaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalQaSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_qa_seed_is_repeatable_and_preserves_inventory_reservations(): void
    {
        $this->seed(LocalQaSeeder::class);

        $counts = [
            Product::count(),
            ProductVariant::count(),
            Inventory::count(),
            ShippingMethod::count(),
            Discount::count(),
        ];

        $inventory = Inventory::query()->where('quantity_on_hand', '>', 2)->firstOrFail();
        $inventory->update(['quantity_reserved' => 1]);

        $this->seed(LocalQaSeeder::class);

        $this->assertSame($counts, [
            Product::count(),
            ProductVariant::count(),
            Inventory::count(),
            ShippingMethod::count(),
            Discount::count(),
        ]);
        $this->assertSame(1, $inventory->fresh()->quantity_reserved);
        $this->assertTrue(User::query()->where('email', 'admin@raoza.test')->where('is_admin', true)->exists());
        $this->assertTrue(ShippingMethod::query()->where('code', 'nl-standard')->where('active', true)->exists());
        $this->assertTrue(Discount::query()->where('code', 'WELCOME10')->where('active', true)->exists());
    }
}
