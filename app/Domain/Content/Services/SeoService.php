<?php

namespace App\Domain\Content\Services;

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Collection;
use App\Domain\Catalog\Models\Product;
use App\Domain\Content\Models\ContentPage;

final class SeoService
{
    public function __construct(private StoreInformation $storeInformation) {}

    public function home(): array
    {
        $store = $this->storeInformation->get();
        $organization = array_filter([
            '@type' => 'Organization',
            '@id' => route('home').'#organization',
            'name' => $store['brand_name'],
            'url' => route('home'),
            'email' => $store['contact_email'],
            'sameAs' => array_values(array_filter([$store['instagram_url'] ?? null])),
        ], fn ($value) => $value !== null && $value !== []);

        return $this->meta(
            'RAOZA — Design-led printed apparel',
            'Explore RAOZA printed T-shirts and hoodies with a modern urban editorial point of view.',
            route('home'),
            null,
            true,
            [
                '@context' => 'https://schema.org',
                '@graph' => [
                    $organization,
                    [
                        '@type' => 'WebSite',
                        '@id' => route('home').'#website',
                        'name' => $store['brand_name'],
                        'url' => route('home'),
                        'publisher' => ['@id' => route('home').'#organization'],
                    ],
                ],
            ],
        );
    }

    public function product(Product $product): array
    {
        $product->loadMissing(['images', 'categories', 'variants.inventory']);
        $prices = $product->variants->map(fn ($variant) => $variant->price_override ?? $product->base_price);
        $available = $product->variants->contains(fn ($variant) => $variant->inventory && ($variant->inventory->quantity_on_hand - $variant->inventory->quantity_reserved) > 0);
        $canonical = $product->canonical_url ?: route('products.show', $product);
        $image = $product->og_image ?: $this->imageUrl($product->images->first()?->path);
        $category = $product->categories->first();

        return $this->meta(
            $product->seo_title ?: $product->name.' — RAOZA',
            $product->seo_description ?: $this->excerpt($product->description),
            $canonical,
            $image,
            $product->indexable,
            [
                '@context' => 'https://schema.org',
                '@graph' => [
                    array_filter([
                        '@type' => 'Product',
                        '@id' => $canonical.'#product',
                        'name' => $product->name,
                        'description' => $this->excerpt($product->description),
                        'image' => $image ? [$image] : null,
                        'brand' => ['@type' => 'Brand', 'name' => config('raoza.brand.name')],
                        'offers' => [
                            '@type' => 'Offer',
                            'url' => $canonical,
                            'priceCurrency' => config('raoza.brand.currency'),
                            'price' => number_format(($prices->min() ?? $product->base_price) / 100, 2, '.', ''),
                            'availability' => $available ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                        ],
                    ], fn ($value) => $value !== null),
                    $this->breadcrumbs(array_values(array_filter([
                        ['name' => 'Home', 'url' => route('home')],
                        ['name' => 'Shop', 'url' => route('shop')],
                        $category ? ['name' => $category->name, 'url' => route('categories.show', $category)] : null,
                        ['name' => $product->name, 'url' => $canonical],
                    ]))),
                ],
            ],
        );
    }

    public function category(Category $category): array
    {
        $canonical = $category->canonical_url ?: route('categories.show', $category);

        return $this->meta(
            $category->seo_title ?: $category->name.' — RAOZA',
            $category->seo_description ?: $this->excerpt($category->description),
            $canonical,
            null,
            $category->indexable,
            ['@context' => 'https://schema.org', '@graph' => [$this->breadcrumbs([
                ['name' => 'Home', 'url' => route('home')],
                ['name' => 'Shop', 'url' => route('shop')],
                ['name' => $category->name, 'url' => $canonical],
            ])]],
        );
    }

    public function collection(Collection $collection): array
    {
        $canonical = $collection->canonical_url ?: route('collections.show', $collection);

        return $this->meta(
            $collection->seo_title ?: $collection->name.' — RAOZA',
            $collection->seo_description ?: $this->excerpt($collection->description),
            $canonical,
            $collection->og_image,
            $collection->indexable,
            ['@context' => 'https://schema.org', '@graph' => [$this->breadcrumbs([
                ['name' => 'Home', 'url' => route('home')],
                ['name' => 'Shop', 'url' => route('shop')],
                ['name' => $collection->name, 'url' => $canonical],
            ])]],
        );
    }

    public function page(ContentPage $page): array
    {
        return $this->meta(
            $page->seo_title ?: $page->title.' — RAOZA',
            $page->seo_description ?: $this->excerpt($page->content['intro'] ?? null),
            $page->canonical_url ?: route('content.show', $page->slug),
            $page->og_image,
            $page->indexable,
        );
    }

    public function basic(string $title, ?string $description, string $canonical, bool $indexable = true): array
    {
        return $this->meta($title, $description, $canonical, null, $indexable);
    }

    private function meta(string $title, ?string $description, string $canonical, ?string $og, bool $indexable, array $jsonLd = []): array
    {
        $indexable = $indexable && app()->environment('production');

        return compact('title', 'description', 'canonical', 'og', 'indexable', 'jsonLd');
    }

    /** @param list<array{name:string,url:string}> $items */
    private function breadcrumbs(array $items): array
    {
        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($items)->values()->map(fn ($item, $index) => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item['name'],
                'item' => $item['url'],
            ])->all(),
        ];
    }

    private function excerpt(?string $value): ?string
    {
        return $value ? mb_substr(trim(strip_tags($value)), 0, 160) : null;
    }

    private function imageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return url(str_starts_with($path, '/') ? $path : '/storage/'.ltrim($path, '/'));
    }
}
