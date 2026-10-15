<?php

namespace Tests\Feature\Storefront;

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Collection;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductOption;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Inventory\Models\Inventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class StorefrontCatalogFilteringTest extends TestCase
{
    use RefreshDatabase;

    public function test_size_and_color_must_match_the_same_active_variant(): void
    {
        $split = $this->product('Split Options', 'split-options', 1, 3000);
        $this->variant($split, 'SPLIT-M-RED', 'M', 'Red', 3000, 5);
        $this->variant($split, 'SPLIT-L-BLUE', 'L', 'Blue', 3000, 5);

        $match = $this->product('Matching Piece', 'matching-piece', 2, 4000);
        $this->variant($match, 'MATCH-M-BLUE', 'M', 'Blue', 4200, 3);

        $inactive = $this->product('Inactive Piece', 'inactive-piece', 3, 4000);
        $this->variant($inactive, 'INACTIVE-M-BLUE', 'M', 'Blue', 4200, 3, 'inactive');

        $this->get('/shop?size[]=M&color[]=Blue')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('products.total', 1)
            ->where('products.data.0.name', 'Matching Piece')
            ->where('filters.size', ['M'])
            ->where('filters.color', ['Blue'])
            ->where('seo.indexable', false));
    }

    public function test_effective_variant_price_and_available_inventory_drive_filters(): void
    {
        $cheap = $this->product('Base Price Piece', 'base-price-piece', 1, 3000);
        $this->variant($cheap, 'BASE-M', 'M', 'Cream', null, 4);

        $available = $this->product('Override Price Piece', 'override-price-piece', 2, 3000);
        $this->variant($available, 'OVERRIDE-M', 'M', 'Cream', 4500, 4, reserved: 1);

        $reserved = $this->product('Fully Reserved Piece', 'fully-reserved-piece', 3, 4500);
        $this->variant($reserved, 'RESERVED-M', 'M', 'Cream', null, 2, reserved: 2);

        $this->get('/shop?min_price=40&max_price=50&in_stock=1')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('products.total', 1)
            ->where('products.data.0.name', 'Override Price Piece')
            ->where('products.data.0.price', 4500)
            ->where('products.data.0.available', true));
    }

    public function test_taxonomy_search_and_sorting_preserve_default_merchandising(): void
    {
        $tees = Category::create(['name' => 'T-Shirts', 'slug' => 't-shirts', 'status' => 'active', 'position' => 1]);
        $drop = Collection::create(['name' => 'Drop', 'slug' => 'drop', 'status' => 'active', 'published_at' => now()->subDay()]);
        $first = $this->product('First % Editorial', 'first-editorial', 1, 5000, now()->subDays(3));
        $second = $this->product('Second Piece', 'second-piece', 2, 2500, now()->subDay());
        $third = $this->product('Third Piece', 'third-piece', 3, 3500, now()->subDays(2));
        foreach ([$first, $second, $third] as $index => $product) {
            $this->variant($product, 'SORT-'.$index, 'M', 'Black', null, 5);
        }
        $first->categories()->attach($tees);
        $first->collections()->attach($drop);

        $this->get('/shop')->assertInertia(fn (Assert $page) => $page->where('products.data.0.name', 'First % Editorial'));
        $this->get('/shop?sort=newest')->assertInertia(fn (Assert $page) => $page->where('products.data.0.name', 'Second Piece'));
        $this->get('/shop?sort=price_asc')->assertInertia(fn (Assert $page) => $page->where('products.data.0.name', 'Second Piece'));
        $this->get('/shop?sort=price_desc')->assertInertia(fn (Assert $page) => $page->where('products.data.0.name', 'First % Editorial'));
        $this->get('/shop?category=t-shirts&collection=drop&q=%25')->assertInertia(fn (Assert $page) => $page
            ->where('products.total', 1)->where('products.data.0.name', 'First % Editorial'));
    }

    public function test_pagination_keeps_filter_and_sort_query_parameters(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            $product = $this->product('Piece '.$i, 'piece-'.$i, $i, 2000 + $i);
            $this->variant($product, 'PAGE-'.$i, 'M', 'Black', null, 2);
        }

        $this->get('/shop?size[]=M&sort=price_desc')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('products.total', 25)
            ->where('products.last_page', 2)
            ->where('products.current_page', 1)
            ->where('products.next_page_url', fn ($url) => str_contains($url, 'size%5B0%5D=M') && str_contains($url, 'sort=price_desc')));
    }

    public function test_catalog_query_parameters_are_bounded_and_validated(): void
    {
        $this->from('/shop')->get('/shop?sort=random')->assertRedirect('/shop')->assertSessionHasErrors('sort');
        $this->from('/shop')->get('/shop?min_price=50&max_price=10')->assertRedirect('/shop')->assertSessionHasErrors('max_price');
        $this->from('/shop')->get('/shop?page=1001')->assertRedirect('/shop')->assertSessionHasErrors('page');
        $sizes = collect(range(1, 13))->map(fn ($i) => 'size[]='.$i)->implode('&');
        $this->from('/shop')->get('/shop?'.$sizes)->assertRedirect('/shop')->assertSessionHasErrors('size');
    }

    private function product(string $name, string $slug, int $position, int $basePrice, $publishedAt = null): Product
    {
        return Product::create(['name' => $name, 'slug' => $slug, 'status' => 'active', 'position' => $position, 'base_price' => $basePrice, 'published_at' => $publishedAt ?? now()->subWeek()]);
    }

    private function variant(Product $product, string $sku, string $size, string $color, ?int $price, int $stock, string $status = 'active', int $reserved = 0): ProductVariant
    {
        $sizeOption = ProductOption::firstOrCreate(['product_id' => $product->id, 'name' => 'Size'], ['position' => 1]);
        $colorOption = ProductOption::firstOrCreate(['product_id' => $product->id, 'name' => 'Color'], ['position' => 2]);
        $sizeValue = $sizeOption->values()->firstOrCreate(['value' => $size], ['position' => $sizeOption->values()->count() + 1]);
        $colorValue = $colorOption->values()->firstOrCreate(['value' => $color], ['position' => $colorOption->values()->count() + 1, 'metadata' => ['swatch' => '#111111']]);
        $variant = $product->variants()->create(['sku' => $sku, 'price_override' => $price, 'status' => $status, 'option_signature' => hash('sha256', $sizeValue->id.'|'.$colorValue->id)]);
        $variant->optionValues()->attach([$sizeValue->id, $colorValue->id]);
        Inventory::create(['variant_id' => $variant->id, 'quantity_on_hand' => $stock, 'quantity_reserved' => $reserved]);

        return $variant;
    }
}
