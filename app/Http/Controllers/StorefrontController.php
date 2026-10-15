<?php

namespace App\Http\Controllers;

use App\Domain\Catalog\Enums\CategoryStatus;
use App\Domain\Catalog\Enums\CollectionStatus;
use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Enums\VariantStatus;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Collection;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductOptionValue;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Content\Services\SeoService;
use App\Domain\Marketing\Services\AnalyticsService;
use App\Http\Requests\StorefrontCatalogRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StorefrontController extends Controller
{
    public function __construct(private SeoService $seo) {}

    public function home(): Response
    {
        $products = $this->publishedProducts()
            ->with(['images', 'categories:id,name,slug', 'collections:id,name,slug', 'variants' => fn ($query) => $query->where('status', VariantStatus::Active)->with('inventory')])
            ->orderBy('position')->orderBy('id')->get()
            ->map(fn (Product $product) => $this->productCard($product));
        $collections = Collection::query()
            ->where('status', CollectionStatus::Active)
            ->where(fn (Builder $q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()))
            ->orderByRaw("CASE slug WHEN 'drop-01' THEN 1 WHEN 'core-essentials' THEN 2 ELSE 3 END")
            ->limit(3)
            ->get(['id', 'name', 'slug', 'description']);

        return Inertia::render('Storefront/Home', ['featuredProducts' => $products, 'collections' => $collections, 'seo' => $this->seo->home()]);
    }

    public function shop(StorefrontCatalogRequest $request): Response
    {
        return $this->browse($request, title: 'Shop', pageType: 'shop', intro: 'A curated selection of RAOZA printed apparel.');
    }

    public function category(StorefrontCatalogRequest $request, Category $category): Response
    {
        abort_unless($category->status === CategoryStatus::Active, 404);

        return $this->browse($request, $category->name, 'category', $category->description ?: 'Explore RAOZA '.$category->name.'.', category: $category);
    }

    public function collection(StorefrontCatalogRequest $request, Collection $collection): Response
    {
        abort_unless($collection->status === CollectionStatus::Active && (! $collection->published_at || $collection->published_at->isPast()), 404);

        return $this->browse($request, $collection->name, 'collection', $collection->description, collection: $collection);
    }

    public function product(Product $product, Request $request, AnalyticsService $analytics): Response
    {
        abort_unless($product->status === ProductStatus::Active && (! $product->published_at || $product->published_at->isPast()), 404);
        $analytics->record($request, 'view_item', ['product_id' => $product->id]);
        $product->load(['images', 'categories', 'collections', 'options.values', 'variants' => fn ($q) => $q->where('status', VariantStatus::Active)->with(['optionValues.option', 'inventory', 'images'])]);

        return Inertia::render('Storefront/Product', ['product' => $this->productDetail($product), 'seo' => $this->seo->product($product)]);
    }

    private function publishedProducts(): Builder
    {
        return Product::query()->where('status', ProductStatus::Active)->where(fn (Builder $q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    private function browse(StorefrontCatalogRequest $request, string $title, string $pageType, ?string $intro, ?Category $category = null, ?Collection $collection = null): Response
    {
        $filters = $request->catalogFilters();
        $query = $this->publishedProducts();
        $this->scopeTaxonomy($query, $category, $collection);

        if ($filters['q'] !== '') {
            $needle = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($filters['q'])).'%';
            $query->where(fn (Builder $q) => $q->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$needle])
                ->orWhereRaw("LOWER(COALESCE(description, '')) LIKE ? ESCAPE '!'", [$needle])
                ->orWhereRaw("LOWER(COALESCE(short_description, '')) LIKE ? ESCAPE '!'", [$needle]));
        }
        if ($filters['category'] !== '') {
            $query->whereHas('categories', fn (Builder $q) => $q->where('slug', $filters['category'])->where('status', CategoryStatus::Active));
        }
        if ($filters['collection'] !== '') {
            $query->whereHas('collections', fn (Builder $q) => $q->where('slug', $filters['collection'])->where('status', CollectionStatus::Active));
        }

        $variantConstraint = fn (Builder $q) => $this->constrainVariants($q, $filters);
        $query->whereHas('variants', $variantConstraint)
            ->with(['images', 'categories:id,name,slug', 'collections:id,name,slug', 'variants' => fn ($q) => $this->constrainVariants($q, $filters, false)->with('inventory')]);

        $priceSubquery = ProductVariant::query()->selectRaw('MIN(COALESCE(product_variants.price_override, products.base_price))')
            ->whereColumn('product_variants.product_id', 'products.id');
        $this->constrainVariants($priceSubquery, $filters);
        $query->select('products.*')->selectSub($priceSubquery, 'filtered_min_price');
        match ($filters['sort']) {
            'newest' => $query->orderByDesc('published_at')->orderByDesc('id'),
            'price_asc' => $query->orderBy('filtered_min_price')->orderBy('position')->orderBy('id'),
            'price_desc' => $query->orderByDesc('filtered_min_price')->orderBy('position')->orderBy('id'),
            default => $query->orderBy('position')->orderBy('id'),
        };

        $products = $query->paginate(24)->withQueryString()->through(fn (Product $product) => $this->productCard($product));
        $seo = $category ? $this->seo->category($category) : ($collection ? $this->seo->collection($collection) : $this->seo->basic('Shop — RAOZA', 'Shop RAOZA printed apparel.', route('shop')));
        if ($request->query() !== []) {
            $seo['indexable'] = false;
        }

        return Inertia::render('Storefront/Shop', [
            'products' => $products, 'filters' => $filters, 'facets' => $this->facets($category, $collection),
            'browsePath' => '/'.ltrim($request->path(), '/'), 'title' => $title, 'pageType' => $pageType, 'intro' => $intro, 'seo' => $seo,
        ]);
    }

    private function constrainVariants($query, array $filters, bool $includePrice = true)
    {
        $query->where('status', VariantStatus::Active);
        foreach (['size' => 'Size', 'color' => 'Color'] as $key => $optionName) {
            if ($filters[$key] !== []) {
                $query->whereHas('optionValues', fn (Builder $q) => $q->whereIn('value', $filters[$key])
                    ->whereHas('option', fn (Builder $option) => $option->whereRaw('LOWER(name) = ?', [mb_strtolower($optionName)])));
            }
        }
        if ($includePrice && $filters['min_price'] !== '') {
            $query->whereRaw('COALESCE(product_variants.price_override, products.base_price) >= ?', [(int) round((float) $filters['min_price'] * 100)]);
        }
        if ($includePrice && $filters['max_price'] !== '') {
            $query->whereRaw('COALESCE(product_variants.price_override, products.base_price) <= ?', [(int) round((float) $filters['max_price'] * 100)]);
        }
        if ($filters['in_stock']) {
            $query->whereHas('inventory', fn (Builder $q) => $q->whereColumn('quantity_on_hand', '>', 'quantity_reserved'));
        }

        return $query;
    }

    private function scopeTaxonomy(Builder $query, ?Category $category, ?Collection $collection): void
    {
        if ($category) {
            $query->whereHas('categories', fn (Builder $q) => $q->whereKey($category->id));
        }
        if ($collection) {
            $query->whereHas('collections', fn (Builder $q) => $q->whereKey($collection->id));
        }
    }

    private function facets(?Category $category, ?Collection $collection): array
    {
        $productScope = function (Builder $query) use ($category, $collection): void {
            $query->where('status', ProductStatus::Active)->where(fn (Builder $q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
            $this->scopeTaxonomy($query, $category, $collection);
        };
        $optionValues = fn (string $name) => ProductOptionValue::query()
            ->whereHas('option', fn (Builder $q) => $q->whereRaw('LOWER(name) = ?', [mb_strtolower($name)]))
            ->whereHas('variants', fn (Builder $q) => $q->where('status', VariantStatus::Active)->whereHas('product', $productScope))
            ->orderBy('position')->orderBy('value')->limit(100)->get(['id', 'value', 'metadata'])->unique('value')->values()
            ->map(fn (ProductOptionValue $value) => ['value' => $value->value, 'swatch' => $value->metadata['swatch'] ?? $value->metadata['hex'] ?? null]);

        return [
            'categories' => Category::query()->where('status', CategoryStatus::Active)->whereHas('products', function (Builder $q) use ($productScope): void {
                $productScope($q);
                $q->whereHas('variants', fn (Builder $v) => $v->where('status', VariantStatus::Active));
            })->orderBy('position')->limit(50)->get(['name', 'slug']),
            'collections' => Collection::query()->where('status', CollectionStatus::Active)->where(fn (Builder $q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()))->whereHas('products', function (Builder $q) use ($productScope): void {
                $productScope($q);
                $q->whereHas('variants', fn (Builder $v) => $v->where('status', VariantStatus::Active));
            })->orderBy('id')->limit(50)->get(['name', 'slug']),
            'sizes' => $optionValues('Size'), 'colors' => $optionValues('Color'),
        ];
    }

    private function productCard(Product $product): array
    {
        $prices = $product->variants->map(fn ($variant) => $variant->price_override ?? $product->base_price);
        $displayPrice = isset($product->filtered_min_price) ? (int) $product->filtered_min_price : ($prices->min() ?? $product->base_price);
        $available = $product->variants->contains(fn ($variant) => $variant->inventory && ($variant->inventory->quantity_on_hand - $variant->inventory->quantity_reserved) > 0);

        return ['id' => $product->id, 'name' => $product->name, 'slug' => $product->slug, 'position' => $product->position, 'shortDescription' => $product->short_description, 'price' => $displayPrice, 'priceVaries' => $prices->unique()->count() > 1, 'image' => $this->imageUrl($product->images->first()?->path), 'imageAlt' => $product->images->first()?->alt_text ?: $product->name, 'imageWidth' => $product->images->first()?->width, 'imageHeight' => $product->images->first()?->height, 'available' => $available,
            'images' => $product->images->map(fn ($image) => ['url' => $this->imageUrl($image->path), 'alt' => $image->alt_text ?: $product->name, 'width' => $image->width, 'height' => $image->height])->values(),
            'category' => $product->categories->first()?->only('name', 'slug'),
            'collection' => $product->collections->first()?->only('name', 'slug'),
        ];
    }

    private function productDetail(Product $product): array
    {
        $activeValueIds = $product->variants->flatMap(fn ($variant) => $variant->optionValues->pluck('id'))->unique();

        return ['id' => $product->id, 'name' => $product->name, 'slug' => $product->slug, 'description' => $product->description, 'shortDescription' => $product->short_description, 'position' => $product->position, 'fitNotes' => $product->fit_notes, 'productDetails' => $product->product_details, 'careInstructions' => $product->care_instructions, 'seoTitle' => $product->seo_title, 'seoDescription' => $product->seo_description, 'basePrice' => $product->base_price,
            'category' => $product->categories->first()?->only('name', 'slug'), 'collection' => $product->collections->first()?->only('name', 'slug'),
            'images' => $product->images->map(fn ($image) => ['id' => $image->id, 'url' => $this->imageUrl($image->path), 'alt' => $image->alt_text ?: $product->name, 'width' => $image->width, 'height' => $image->height])->values(),
            'options' => $product->options->map(fn ($option) => ['id' => $option->id, 'name' => $option->name, 'values' => $option->values->whereIn('id', $activeValueIds)->map(fn ($value) => ['id' => $value->id, 'value' => $value->value, 'metadata' => $value->metadata])->values()])->filter(fn ($option) => $option['values']->isNotEmpty())->values(),
            'variants' => $product->variants->map(function ($variant) use ($product) {
                $available = max(0, ($variant->inventory?->quantity_on_hand ?? 0) - ($variant->inventory?->quantity_reserved ?? 0));

                return ['id' => $variant->id, 'sku' => $variant->sku, 'price' => $variant->price_override ?? $product->base_price, 'availableQuantity' => $available, 'available' => $available > 0, 'optionValueIds' => $variant->optionValues->pluck('id')->values(), 'options' => $variant->optionValues->mapWithKeys(fn ($value) => [$value->option->name => $value->value]), 'images' => $variant->images->map(fn ($image) => ['url' => $this->imageUrl($image->path), 'alt' => $image->alt_text ?: $product->name, 'width' => $image->width, 'height' => $image->height])->values()];
            })->values()];
    }

    private function imageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
            return $path;
        }

        return '/storage/'.ltrim($path,'/');
    }
}
