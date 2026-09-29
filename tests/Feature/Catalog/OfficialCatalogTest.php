<?php

namespace Tests\Feature\Catalog;

use App\Domain\Catalog\Models\Product;
use Database\Seeders\LocalQaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OfficialCatalogTest extends TestCase
{
    use RefreshDatabase;

    private const NAMES = [
        'RAOZA Signature Tee',
        'RAOZA Editorial Tee',
        'RAOZA Mark Tee',
        'RAOZA Essential Hoodie',
        'RAOZA Structured Hoodie',
        'RAOZA Deep Burgundy Hoodie',
    ];

    public function test_official_catalog_is_exactly_six_products_in_approved_order(): void
    {
        $this->seed(LocalQaSeeder::class);

        $products = Product::query()->where('status', 'active')->orderBy('position')->get();

        $this->assertSame(self::NAMES, $products->pluck('name')->all());
        $this->assertSame(range(1, 6), $products->pluck('position')->all());
        $this->assertSame(6, $products->pluck('slug')->unique()->count());
        $this->assertSame(1, $products->where('name', 'RAOZA Deep Burgundy Hoodie')->count());
        $this->assertSame([5, 5, 6, 5, 5, 4], $products->map(fn (Product $product) => $product->images()->count())->all());

        $this->get('/shop')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Storefront/Shop')
            ->has('products.data', 6)
            ->where('products.data.0.name', self::NAMES[0])
            ->where('products.data.5.name', self::NAMES[5]));
    }

    public function test_categories_collections_and_optional_care_match_approved_catalog(): void
    {
        $this->seed(LocalQaSeeder::class);

        $this->assertSame(3, Product::query()->where('status', 'active')->whereHas('categories', fn ($query) => $query->where('slug', 't-shirts'))->count());
        $this->assertSame(3, Product::query()->where('status', 'active')->whereHas('categories', fn ($query) => $query->where('slug', 'hoodies'))->count());
        $this->assertSame(2, Product::query()->where('status', 'active')->whereHas('collections', fn ($query) => $query->where('slug', 'drop-01'))->count());
        $this->assertSame(4, Product::query()->where('status', 'active')->whereHas('collections', fn ($query) => $query->where('slug', 'core-essentials'))->count());
        $this->assertSame(0, Product::query()->where('status', 'active')->whereNotNull('care_instructions')->count());

        $hoodie = Product::query()->where('slug', 'raoza-deep-burgundy-hoodie')->firstOrFail();
        $this->get('/products/'.$hoodie->slug)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Storefront/Product')
            ->where('product.name', 'RAOZA Deep Burgundy Hoodie')
            ->where('product.careInstructions', null)
            ->where('product.position', 6)
            ->has('product.images', 4)
            ->where('seo.jsonLd.@graph.0.name', 'RAOZA Deep Burgundy Hoodie')
            ->where('seo.og', url('/catalog/deep-burgundy-hoodie/raoza-deep-burgundy-hoodie-front.png')));
    }

    public function test_official_catalog_seeding_is_idempotent(): void
    {
        $this->seed(LocalQaSeeder::class);
        $ids = Product::query()->where('status', 'active')->orderBy('position')->pluck('id')->all();

        $this->seed(LocalQaSeeder::class);

        $this->assertSame($ids, Product::query()->where('status', 'active')->orderBy('position')->pluck('id')->all());
        $this->assertSame(6, Product::query()->count());
        $this->assertSame(30, Product::query()->withCount('images')->get()->sum('images_count'));
    }
}
