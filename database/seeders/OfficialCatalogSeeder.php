<?php

namespace Database\Seeders;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Enums\VariantStatus;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Collection;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductOptionValue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class OfficialCatalogSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $tees = Category::query()->where('slug', 't-shirts')->firstOrFail();
            $hoodies = Category::query()->where('slug', 'hoodies')->firstOrFail();
            $drop = Collection::query()->where('slug', 'drop-01')->firstOrFail();
            $core = Collection::query()->where('slug', 'core-essentials')->firstOrFail();

            $officialIds = [];

            foreach ($this->catalog() as $item) {
                $product = Product::query()->where('slug', $item['slug'])->first()
                    ?? Product::query()->where('slug', $item['legacy_slug'])->first();

                if (! $product) {
                    throw new RuntimeException("Cannot safely map official product {$item['position']} to an existing commerce record.");
                }

                $product->update([
                    'name' => $item['name'],
                    'slug' => $item['slug'],
                    'short_description' => $item['short_description'],
                    'description' => $item['description'],
                    'fit_notes' => $item['fit_notes'],
                    'product_details' => implode("\n", $item['product_details']),
                    'care_instructions' => null,
                    'status' => ProductStatus::Active,
                    'position' => $item['position'],
                    'seo_title' => $item['name'].' — RAOZA',
                    'seo_description' => $item['short_description'],
                    'canonical_url' => null,
                    'og_image' => $item['images'][0][0],
                    'indexable' => true,
                    'published_at' => $product->published_at ?? now(),
                ]);

                $category = $item['category'] === 't-shirts' ? $tees : $hoodies;
                $collection = $item['collection'] === 'drop-01' ? $drop : $core;
                $product->categories()->sync([$category->id => ['position' => $item['position']]]);
                $product->collections()->sync([$collection->id => ['position' => $item['position']]]);

                $paths = collect($item['images'])->pluck(0)->all();
                $product->images()->whereNull('variant_id')->whereNotIn('path', $paths)->delete();
                foreach ($item['images'] as $position => [$path, $alt]) {
                    $absolute = public_path(ltrim($path, '/'));
                    if (! is_file($absolute)) {
                        throw new RuntimeException("Official catalog image is missing: {$path}");
                    }
                    [$width, $height] = getimagesize($absolute);
                    $product->images()->updateOrCreate(
                        ['path' => $path],
                        ['variant_id' => null, 'alt_text' => $alt, 'position' => $position, 'width' => $width, 'height' => $height],
                    );
                }

                if ($item['official_color']) {
                    $this->limitToOfficialColor($product, ...$item['official_color']);
                }

                $officialIds[] = $product->id;
            }

            Product::query()
                ->where('status', ProductStatus::Active)
                ->whereNotIn('id', $officialIds)
                ->update(['status' => ProductStatus::Archived, 'published_at' => null, 'indexable' => false]);
        });
    }

    private function limitToOfficialColor(Product $product, array $acceptedNames, string $officialName, string $swatch): void
    {
        $color = $product->options()->where('name', 'Color')->first();
        if (! $color) {
            return;
        }

        $selected = $color->values()->whereIn('value', $acceptedNames)->first();
        if (! $selected) {
            return;
        }

        $otherIds = $color->values()->where('id', '!=', $selected->id)->pluck('id');
        $product->variants()
            ->whereHas('optionValues', fn ($query) => $query->whereIn('product_option_values.id', $otherIds))
            ->update(['status' => VariantStatus::Inactive]);

        ProductOptionValue::query()->whereKey($selected->id)->update([
            'value' => $officialName,
            'metadata' => ['swatch' => $swatch],
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function catalog(): array
    {
        return [
            [
                'position' => 1,
                'legacy_slug' => 'editorial-mark-tee',
                'slug' => 'raoza-signature-tee',
                'name' => 'RAOZA Signature Tee',
                'category' => 't-shirts',
                'collection' => 'core-essentials',
                'short_description' => 'A refined everyday essential built around the RAOZA identity.',
                'description' => "A refined everyday essential built around the RAOZA identity. The Signature Tee combines a relaxed silhouette with clean brand detailing and a premium minimal finish. Designed to stand on its own or layer effortlessly, it brings RAOZA's Urban Editorial character into an understated everyday piece.",
                'fit_notes' => 'Relaxed / Slightly Oversized',
                'product_details' => ['Minimal / Premium / Brand-led'],
                'official_color' => null,
                'images' => [
                    ['/catalog/signature-tee/raoza-signature-tee-front.png', 'RAOZA Signature Tee front view'],
                    ['/catalog/signature-tee/raoza-signature-tee-back.png', 'RAOZA Signature Tee back view'],
                    ['/catalog/signature-tee/raoza-signature-tee-detail.png', 'RAOZA Signature Tee wordmark detail'],
                    ['/catalog/signature-tee/raoza-signature-tee-editorial.png', 'Model wearing the RAOZA Signature Tee'],
                    ['/catalog/signature-tee/raoza-signature-tee-lifestyle.png', 'RAOZA Signature Tee styled in an editorial setting'],
                ],
            ],
            [
                'position' => 2,
                'legacy_slug' => 'after-dark-tee',
                'slug' => 'raoza-editorial-tee',
                'name' => 'RAOZA Editorial Tee',
                'category' => 't-shirts',
                'collection' => 'drop-01',
                'short_description' => 'A graphic-led expression of the RAOZA visual language.',
                'description' => 'A graphic-led expression of the RAOZA visual language. The Editorial Tee combines a relaxed fit with a stronger fashion-focused composition, balancing statement design with a controlled, wearable silhouette. Created for the more expressive side of the RAOZA wardrobe.',
                'fit_notes' => 'Relaxed / Slightly Oversized',
                'product_details' => ['Editorial / Graphic-led / Urban'],
                'official_color' => null,
                'images' => [
                    ['/catalog/editorial-tee/raoza-editorial-tee-front.png', 'RAOZA Editorial Tee front view'],
                    ['/catalog/editorial-tee/raoza-editorial-tee-back.png', 'RAOZA Editorial Tee back graphic'],
                    ['/catalog/editorial-tee/raoza-editorial-tee-detail.png', 'RAOZA Editorial Tee monogram detail'],
                    ['/catalog/editorial-tee/raoza-editorial-tee-editorial.png', 'Model showing the back of the RAOZA Editorial Tee'],
                    ['/catalog/editorial-tee/raoza-editorial-tee-lifestyle.png', 'Model wearing the RAOZA Editorial Tee'],
                ],
            ],
            [
                'position' => 3,
                'legacy_slug' => 'studio-type-tee',
                'slug' => 'raoza-mark-tee',
                'name' => 'RAOZA Mark Tee',
                'category' => 't-shirts',
                'collection' => 'core-essentials',
                'short_description' => 'A minimal RAOZA essential centered around the signature monogram.',
                'description' => 'A minimal RAOZA essential centered around the brand monogram. The Mark Tee pairs a relaxed silhouette with restrained signature detailing, allowing the RA mark and deep brand color to carry the design. Clean, confident and distinctly RAOZA.',
                'fit_notes' => 'Relaxed / Slightly Oversized',
                'product_details' => ['Deep Burgundy', 'RA monogram', 'Monogram-led / Minimal / Premium'],
                'official_color' => null,
                'images' => [
                    ['/catalog/mark-tee/raoza-mark-tee-front.png', 'Deep Burgundy RAOZA Mark Tee front view'],
                    ['/catalog/mark-tee/raoza-mark-tee-back.png', 'Deep Burgundy RAOZA Mark Tee back view'],
                    ['/catalog/mark-tee/raoza-mark-tee-detail.png', 'RAOZA Mark Tee monogram detail'],
                    ['/catalog/mark-tee/raoza-mark-tee-editorial.png', 'Model wearing the RAOZA Mark Tee'],
                    ['/catalog/mark-tee/raoza-mark-tee-back-editorial.png', 'Model showing the back of the RAOZA Mark Tee'],
                    ['/catalog/mark-tee/raoza-mark-tee-lifestyle.png', 'Woman wearing the unisex RAOZA Mark Tee'],
                ],
            ],
            [
                'position' => 4,
                'legacy_slug' => 'ra-monogram-hoodie',
                'slug' => 'raoza-essential-hoodie',
                'name' => 'RAOZA Essential Hoodie',
                'category' => 'hoodies',
                'collection' => 'core-essentials',
                'short_description' => 'A heavyweight everyday layer with a restrained RAOZA finish.',
                'description' => 'A heavyweight everyday layer with a restrained RAOZA finish. The Essential Hoodie features a relaxed oversized silhouette, structured hood and minimal signature branding. Built around comfort, proportion and quiet identity rather than oversized graphics.',
                'fit_notes' => 'Relaxed / Oversized',
                'product_details' => ['Dropped shoulder', 'Structured hood', 'Small RAOZA wordmark at front', 'Four-point sparkle detail at back'],
                'official_color' => [['Black', 'Near Black'], 'Near Black', '#111111'],
                'images' => [
                    ['/catalog/essential-hoodie/raoza-essential-hoodie-front.png', 'Model wearing the Near Black RAOZA Essential Hoodie'],
                    ['/catalog/essential-hoodie/raoza-essential-hoodie-back.png', 'RAOZA Essential Hoodie back sparkle detail'],
                    ['/catalog/essential-hoodie/raoza-essential-hoodie-detail.png', 'RAOZA Essential Hoodie wordmark detail'],
                    ['/catalog/essential-hoodie/raoza-essential-hoodie-editorial.png', 'Man wearing the RAOZA Essential Hoodie'],
                    ['/catalog/essential-hoodie/raoza-essential-hoodie-lifestyle.png', 'Woman wearing the RAOZA Essential Hoodie'],
                ],
            ],
            [
                'position' => 5,
                'legacy_slug' => 'quiet-signal-hoodie',
                'slug' => 'raoza-structured-hoodie',
                'name' => 'RAOZA Structured Hoodie',
                'category' => 'hoodies',
                'collection' => 'core-essentials',
                'short_description' => 'A structured interpretation of the RAOZA hoodie.',
                'description' => 'A structured interpretation of the RAOZA hoodie. Cut in a boxy oversized silhouette, the Structured Hoodie combines warm cream with deep burgundy branding for a clean architectural contrast. Subtle front detailing and a stronger back identity create balance from every angle.',
                'fit_notes' => 'Boxy / Oversized',
                'product_details' => ['Dropped shoulder', 'Structured hood', 'RA monogram at left chest', 'RAOZA wordmark at back'],
                'official_color' => [['Cream', 'Warm Cream'], 'Warm Cream', '#FFF6E6'],
                'images' => [
                    ['/catalog/structured-hoodie/raoza-structured-hoodie-front.png', 'Model wearing the Warm Cream RAOZA Structured Hoodie'],
                    ['/catalog/structured-hoodie/raoza-structured-hoodie-back.png', 'RAOZA Structured Hoodie back wordmark'],
                    ['/catalog/structured-hoodie/raoza-structured-hoodie-detail.png', 'RAOZA Structured Hoodie monogram detail'],
                    ['/catalog/structured-hoodie/raoza-structured-hoodie-editorial.png', 'Woman wearing the RAOZA Structured Hoodie'],
                    ['/catalog/structured-hoodie/raoza-structured-hoodie-lifestyle.png', 'RAOZA Structured Hoodie full-length styling'],
                ],
            ],
            [
                'position' => 6,
                'legacy_slug' => 'archive-01-hoodie',
                'slug' => 'raoza-deep-burgundy-hoodie',
                'name' => 'RAOZA Deep Burgundy Hoodie',
                'category' => 'hoodies',
                'collection' => 'drop-01',
                'short_description' => "A signature hoodie built around RAOZA's deepest brand color.",
                'description' => "The deepest expression of the RAOZA palette. The Deep Burgundy Hoodie combines a heavyweight relaxed silhouette with muted gold signature detailing, creating a rich contrast without sacrificing restraint. Designed as a unisex statement essential, it brings together comfort, identity and RAOZA's Urban Editorial character.",
                'fit_notes' => 'Relaxed / Oversized',
                'product_details' => ['Unisex presentation', 'Deep Burgundy', 'Muted Gold RA monogram at front', 'Muted Gold RAOZA wordmark at back'],
                'official_color' => [['Burgundy', 'Deep Burgundy'], 'Deep Burgundy', '#330313'],
                'images' => [
                    ['/catalog/deep-burgundy-hoodie/raoza-deep-burgundy-hoodie-front.png', 'Man wearing the unisex RAOZA Deep Burgundy Hoodie'],
                    ['/catalog/deep-burgundy-hoodie/raoza-deep-burgundy-hoodie-back.png', 'RAOZA Deep Burgundy Hoodie muted gold back wordmark'],
                    ['/catalog/deep-burgundy-hoodie/raoza-deep-burgundy-hoodie-detail.png', 'RAOZA Deep Burgundy Hoodie muted gold monogram detail'],
                    ['/catalog/deep-burgundy-hoodie/raoza-deep-burgundy-hoodie-editorial.png', 'Man wearing the unisex RAOZA Deep Burgundy Hoodie in an editorial setting'],
                ],
            ],
        ];
    }
}
