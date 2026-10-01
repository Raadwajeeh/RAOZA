<?php

namespace App\Domain\Catalog\Services;

use App\Domain\Catalog\Enums\CategoryStatus;
use App\Domain\Catalog\Enums\CollectionStatus;
use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Enums\VariantStatus;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Collection;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductOption;
use App\Domain\Catalog\Models\ProductOptionValue;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Catalog\VariantSignature;
use App\Domain\Content\Models\SiteContent;
use App\Domain\Fulfillment\Models\ShippingMethod;
use App\Domain\Inventory\Enums\InventoryMovementType;
use App\Domain\Inventory\Models\Inventory;
use App\Domain\Inventory\Models\InventoryMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

final class ProductionBootstrapService
{
    public const PRODUCT_SLUGS = [
        'raoza-signature-tee', 'raoza-editorial-tee', 'raoza-mark-tee',
        'raoza-essential-hoodie', 'raoza-structured-hoodie', 'raoza-deep-burgundy-hoodie',
    ];

    /** @return array{data:array<string,mixed>,summary:array<string,int>} */
    public function validateManifest(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('Bootstrap manifest was not found.');
        }
        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data)) {
            throw new RuntimeException('Bootstrap manifest must be valid JSON.');
        }
        $this->rejectSecrets($data);
        $validator = Validator::make($data, [
            'version' => ['required','integer','in:1'],
            'categories' => ['required','array','min:1'],
            'categories.*.slug' => ['required','alpha_dash','distinct'],
            'categories.*.name' => ['required','string','max:120'],
            'categories.*.description' => ['required','string'],
            'collections' => ['required','array','min:1'],
            'collections.*.slug' => ['required','alpha_dash','distinct'],
            'collections.*.name' => ['required','string','max:120'],
            'collections.*.description' => ['required','string'],
            'products' => ['required','array','size:6'],
            'products.*.slug' => ['required','alpha_dash','distinct'],
            'products.*.name' => ['required','string','max:160'],
            'products.*.category_slug' => ['required','string'],
            'products.*.collection_slug' => ['required','string'],
            'products.*.position' => ['required','integer','between:1,6','distinct'],
            'products.*.base_price' => ['required','integer','min:1'],
            'products.*.short_description' => ['required','string','max:500'],
            'products.*.description' => ['required','string'],
            'products.*.fit_notes' => ['required','string'],
            'products.*.product_details' => ['required','string'],
            'products.*.care_instructions' => ['required','string'],
            'products.*.seo_title' => ['required','string','max:255'],
            'products.*.seo_description' => ['required','string','max:500'],
            'products.*.images' => ['required','array','min:1'],
            'products.*.images.*.path' => ['required','string','max:2048'],
            'products.*.images.*.alt_text' => ['required','string','max:255'],
            'products.*.variants' => ['required','array','min:1'],
            'products.*.variants.*.sku' => ['required','string','max:120'],
            'products.*.variants.*.price_override' => ['nullable','integer','min:0'],
            'products.*.variants.*.opening_stock' => ['required','integer','min:0'],
            'products.*.variants.*.options' => ['required','array','min:1'],
            'store' => ['sometimes','array'],
            'shipping_methods' => ['sometimes','array'],
            'shipping_methods.*.code' => ['required','alpha_dash'],
            'shipping_methods.*.name' => ['required','string','max:120'],
            'shipping_methods.*.provider' => ['required','string','max:80'],
            'shipping_methods.*.price' => ['required','integer','min:0'],
            'shipping_methods.*.currency' => ['required','string','size:3'],
        ]);
        if ($validator->fails()) {
            throw new RuntimeException("Bootstrap manifest is incomplete:\n- ".implode("\n- ", $validator->errors()->all()));
        }
        if (array_values(array_unique(array_column($data['products'], 'slug'))) !== self::PRODUCT_SLUGS) {
            throw new RuntimeException('Bootstrap manifest must contain the six approved products in canonical order.');
        }
        $categorySlugs = array_column($data['categories'], 'slug');
        $collectionSlugs = array_column($data['collections'], 'slug');
        $skus = [];
        foreach ($data['products'] as $product) {
            if (! in_array($product['category_slug'], $categorySlugs, true) || ! in_array($product['collection_slug'], $collectionSlugs, true)) {
                throw new RuntimeException("Product {$product['slug']} references an undefined category or collection.");
            }
            foreach ($product['images'] as $image) {
                if (str_starts_with($image['path'], '/') && ! is_file(public_path(ltrim($image['path'], '/')))) {
                    throw new RuntimeException("Catalog image does not exist: {$image['path']}");
                }
            }
            foreach ($product['variants'] as $variant) {
                if (isset($skus[strtoupper($variant['sku'])])) {
                    throw new RuntimeException("Duplicate SKU in manifest: {$variant['sku']}");
                }
                $skus[strtoupper($variant['sku'])] = true;
            }
        }

        return ['data'=>$data, 'summary'=>[
            'categories'=>count($data['categories']), 'collections'=>count($data['collections']),
            'products'=>count($data['products']), 'variants'=>array_sum(array_map(fn ($p) => count($p['variants']), $data['products'])),
            'shipping_methods'=>count($data['shipping_methods'] ?? []),
        ]];
    }

    /** @return array<string,int> */
    public function apply(array $data): array
    {
        if (config('commerce.demo_mode')) {
            throw new RuntimeException('Production bootstrap refuses to run while RAOZA_DEMO_MODE is enabled.');
        }

        return DB::transaction(function () use ($data): array {
            $categories = collect($data['categories'])->mapWithKeys(function (array $row) {
                $category = Category::query()->updateOrCreate(['slug'=>$row['slug']], [
                    'name'=>$row['name'],'description'=>$row['description'],'status'=>CategoryStatus::Active,
                    'position'=>$row['position'] ?? 0,'seo_title'=>$row['seo_title'] ?? null,
                    'seo_description'=>$row['seo_description'] ?? null,'indexable'=>true,
                ]);
                return [$row['slug']=>$category];
            });
            $collections = collect($data['collections'])->mapWithKeys(function (array $row) {
                $collection = Collection::query()->updateOrCreate(['slug'=>$row['slug']], [
                    'name'=>$row['name'],'description'=>$row['description'],'status'=>CollectionStatus::Active,
                    'published_at'=>now(),'seo_title'=>$row['seo_title'] ?? null,
                    'seo_description'=>$row['seo_description'] ?? null,'indexable'=>true,
                ]);
                return [$row['slug']=>$collection];
            });

            foreach ($data['products'] as $row) {
                $product = Product::query()->updateOrCreate(['slug'=>$row['slug']], [
                    'name'=>$row['name'],'position'=>$row['position'],'base_price'=>$row['base_price'],
                    'short_description'=>$row['short_description'],'description'=>$row['description'],
                    'fit_notes'=>$row['fit_notes'],'product_details'=>$row['product_details'],
                    'care_instructions'=>$row['care_instructions'],'status'=>ProductStatus::Active,
                    'seo_title'=>$row['seo_title'],'seo_description'=>$row['seo_description'],
                    'og_image'=>$row['images'][0]['path'],'indexable'=>true,'published_at'=>now(),
                ]);
                $product->categories()->sync([$categories[$row['category_slug']]->id=>['position'=>$row['position']]]);
                $product->collections()->sync([$collections[$row['collection_slug']]->id=>['position'=>$row['position']]]);
                foreach ($row['images'] as $position => $image) {
                    $product->images()->updateOrCreate(['path'=>$image['path']], [
                        'variant_id'=>null,'alt_text'=>$image['alt_text'],'position'=>$position,
                        'width'=>$image['width'] ?? null,'height'=>$image['height'] ?? null,
                    ]);
                }
                foreach ($row['variants'] as $variantData) {
                    $valueIds = [];
                    foreach ($variantData['options'] as $optionName => $valueName) {
                        $option = ProductOption::query()->firstOrCreate(['product_id'=>$product->id,'name'=>$optionName]);
                        $value = ProductOptionValue::query()->firstOrCreate(['product_option_id'=>$option->id,'value'=>$valueName]);
                        $valueIds[] = $value->id;
                    }
                    $conflict = ProductVariant::query()->where('sku',$variantData['sku'])->where('product_id','!=',$product->id)->exists();
                    if ($conflict) throw new RuntimeException("SKU belongs to another product: {$variantData['sku']}");
                    $variant = ProductVariant::query()->updateOrCreate(['product_id'=>$product->id,'option_signature'=>VariantSignature::fromOptionValueIds($valueIds)], [
                        'sku'=>$variantData['sku'],'price_override'=>$variantData['price_override'] ?? null,'status'=>VariantStatus::Active,
                    ]);
                    $variant->optionValues()->sync($valueIds);
                    $inventory = Inventory::query()->firstOrCreate(['variant_id'=>$variant->id], ['quantity_on_hand'=>$variantData['opening_stock'],'quantity_reserved'=>0]);
                    if (! $inventory->wasRecentlyCreated && $inventory->quantity_on_hand !== $variantData['opening_stock']) {
                        throw new RuntimeException("Existing stock for {$variant->sku} differs from the manifest; use an audited inventory adjustment.");
                    }
                    InventoryMovement::query()->firstOrCreate(
                        ['variant_id'=>$variant->id,'reference_type'=>'production_bootstrap','reference_id'=>'opening'],
                        ['type'=>InventoryMovementType::StockReceived,'quantity'=>$variantData['opening_stock'],'note'=>'Reviewed production opening stock','created_at'=>now()],
                    );
                }
            }
            if (isset($data['store'])) SiteContent::query()->updateOrCreate(['key'=>'store_information'], ['value'=>$data['store']]);
            foreach ($data['shipping_methods'] ?? [] as $row) {
                ShippingMethod::query()->updateOrCreate(['code'=>$row['code']], [
                    'name'=>$row['name'],'provider'=>$row['provider'],'price'=>$row['price'],
                    'currency'=>strtoupper($row['currency']),'active'=>$row['active'] ?? true,
                    'position'=>$row['position'] ?? 0,'configuration'=>$row['configuration'] ?? null,
                ]);
            }
            return ['categories'=>$categories->count(),'collections'=>$collections->count(),'products'=>count($data['products'])];
        }, 3);
    }

    private function rejectSecrets(array $data, string $path = ''): void
    {
        foreach ($data as $key => $value) {
            $current = $path === '' ? (string) $key : $path.'.'.$key;
            if (preg_match('/(?:password|secret|api.?key|token|credential)/i', (string) $key)) {
                throw new RuntimeException("Secrets are not allowed in the bootstrap manifest ({$current}).");
            }
            if (is_array($value)) $this->rejectSecrets($value, $current);
        }
    }
}
