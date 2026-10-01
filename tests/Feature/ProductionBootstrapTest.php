<?php

namespace Tests\Feature;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Services\ProductionBootstrapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ProductionBootstrapTest extends TestCase
{
    use RefreshDatabase;

    public function test_incomplete_example_is_validation_only_and_writes_nothing(): void
    {
        $this->artisan('production:bootstrap', ['manifest'=>'resources/production/bootstrap-manifest.example.json','--dry-run'=>true])
            ->assertFailed();
        $this->assertDatabaseCount('products', 0);
    }

    public function test_reviewed_manifest_is_portable_idempotent_and_refuses_demo_mode(): void
    {
        $path = $this->manifest();
        $this->app['config']->set('commerce.demo_mode', true);
        $this->expectException(RuntimeException::class);
        try {
            $validated = app(ProductionBootstrapService::class)->validateManifest($path);
            app(ProductionBootstrapService::class)->apply($validated['data']);
        } finally {
            @unlink($path);
        }
    }

    public function test_reviewed_manifest_creates_six_products_without_legacy_ids(): void
    {
        $path = $this->manifest();
        $this->app['config']->set('commerce.demo_mode', false);
        try {
            $service = app(ProductionBootstrapService::class);
            $validated = $service->validateManifest($path);
            $service->apply($validated['data']);
            $service->apply($validated['data']);
        } finally {
            @unlink($path);
        }
        $this->assertSame(ProductionBootstrapService::PRODUCT_SLUGS, Product::query()->orderBy('position')->pluck('slug')->all());
        $this->assertDatabaseCount('products', 6);
        $this->assertDatabaseCount('product_variants', 6);
        $this->assertDatabaseCount('inventory_movements', 6);
    }

    private function manifest(): string
    {
        $products = collect(ProductionBootstrapService::PRODUCT_SLUGS)->values()->map(fn ($slug,$index) => [
            'slug'=>$slug,'name'=>ucwords(str_replace('-',' ',$slug)),
            'category_slug'=>$index < 3 ? 't-shirts' : 'hoodies',
            'collection_slug'=>$index % 2 ? 'drop-01' : 'core-essentials','position'=>$index+1,
            'base_price'=>3000+$index,'short_description'=>'Reviewed short description','description'=>'Reviewed description',
            'fit_notes'=>'Reviewed fit','product_details'=>'Reviewed details','care_instructions'=>'Reviewed care',
            'seo_title'=>'Reviewed title','seo_description'=>'Reviewed SEO description',
            'images'=>[['path'=>'/brand/raoza-monogram-gold.png','alt_text'=>'Reviewed product image']],
            'variants'=>[['sku'=>'PROD-'.($index+1),'price_override'=>null,'opening_stock'=>5,'options'=>['Size'=>'M','Color'=>'Reviewed']]],
        ])->all();
        $data = ['version'=>1,
            'categories'=>[['slug'=>'t-shirts','name'=>'T-Shirts','description'=>'Reviewed'],['slug'=>'hoodies','name'=>'Hoodies','description'=>'Reviewed']],
            'collections'=>[['slug'=>'drop-01','name'=>'Drop 01','description'=>'Reviewed'],['slug'=>'core-essentials','name'=>'Core Essentials','description'=>'Reviewed']],
            'products'=>$products,
        ];
        $path = tempnam(sys_get_temp_dir(), 'raoza-bootstrap-');
        file_put_contents($path, json_encode($data, JSON_THROW_ON_ERROR));
        return $path;
    }
}
