<?php

namespace Tests\Feature\Storefront;

use App\Domain\Catalog\Models\Product;
use Database\Seeders\LocalQaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontAssetReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_ships_complete_webp_sets_without_deploying_png_sources(): void
    {
        $catalogRoot = public_path('catalog');
        $pngSources = glob($catalogRoot.'/*/*.png') ?: [];
        $webpFiles = glob($catalogRoot.'/*/*.webp') ?: [];
        $baseImages = array_values(array_filter(
            $webpFiles,
            fn (string $path): bool => ! preg_match('/-(640|960)\.webp$/', $path),
        ));

        $this->assertCount(0, $pngSources);
        $this->assertCount(90, $webpFiles);
        $this->assertCount(30, $baseImages);

        foreach ($baseImages as $baseImage) {
            $stem = substr($baseImage, 0, -5);
            $this->assertFileExists($stem.'-640.webp');
            $this->assertFileExists($stem.'-960.webp');
        }
    }

    public function test_versioned_brand_and_campaign_images_are_real_public_images(): void
    {
        $paths = [
            'brand/raoza-wordmark-light.png',
            'brand/raoza-wordmark-burgundy.png',
            'campaign/home-hero-desktop.webp',
            'campaign/home-hero-mobile.webp',
            'campaign/editorial-wide.webp',
            'campaign/tshirts-campaign.webp',
            'campaign/hoodies-campaign.webp',
            'campaign/drop01-campaign.webp',
            'campaign/core-essentials-campaign.webp',
        ];

        foreach ($paths as $path) {
            $absolute = public_path($path);
            $this->assertFileExists($absolute, "Missing storefront asset: {$path}");
            $this->assertNotFalse(getimagesize($absolute), "Invalid storefront image: {$path}");
        }
    }

    public function test_seeded_catalog_image_urls_match_existing_case_sensitive_paths(): void
    {
        $this->seed(LocalQaSeeder::class);

        $images = Product::query()
            ->where('status', 'active')
            ->with('images')
            ->get()
            ->flatMap->images;

        $this->assertCount(30, $images);

        foreach ($images as $image) {
            $this->assertStringStartsWith('/catalog/', $image->path);
            $this->assertStringEndsWith('.webp', $image->path);
            $absolute = public_path(ltrim($image->path, '/'));
            $this->assertFileExists($absolute, "Missing catalog image: {$image->path}");
            $dimensions = getimagesize($absolute);
            $this->assertNotFalse($dimensions, "Invalid catalog image: {$image->path}");
            $this->assertSame($image->width, $dimensions[0]);
            $this->assertSame($image->height, $dimensions[1]);
        }
    }
}
