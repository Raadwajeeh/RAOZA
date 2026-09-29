<?php

namespace Tests\Feature\Storefront;

use App\Domain\Catalog\Models\Product;
use App\Domain\Content\Models\ContentPage;
use App\Domain\Content\Models\SiteContent;
use App\Domain\Content\Services\StoreInformation;
use App\Domain\Marketing\Models\AnalyticsEvent;
use App\Domain\Marketing\Models\ConsentRecord;
use Database\Seeders\LocalQaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StorefrontCompletenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_information_pages_and_discovery_files_are_complete(): void
    {
        $this->seed(LocalQaSeeder::class);

        foreach (['about', 'contact', 'faq', 'shipping', 'returns', 'privacy', 'cookies', 'terms', 'size-guide'] as $slug) {
            $this->get('/pages/'.$slug)
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Storefront/ContentPage')
                    ->where('page.slug', $slug)
                    ->has('page.content.sections'));
        }

        $sitemap = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml');
        $sitemap->assertSee('/products/')->assertSee('/categories/')->assertSee('/collections/')->assertSee('/pages/privacy');
        $sitemap->assertDontSee('/admin')->assertDontSee('/checkout')->assertDontSee('/cart');

        $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Disallow: /');
    }

    public function test_product_seo_and_funnel_analytics_use_real_catalog_data_and_consent(): void
    {
        $this->seed(LocalQaSeeder::class);
        $product = Product::query()->where('status', 'active')->with('variants.inventory')->firstOrFail();

        $this->get('/products/'.$product->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Storefront/Product')
                ->where('product.name', $product->name)
                ->where('seo.jsonLd.@graph.0.name', $product->name)
                ->where('seo.jsonLd.@graph.0.offers.priceCurrency', 'EUR')
                ->where('seo.indexable', false));
        $this->assertDatabaseMissing('analytics_events', ['event_name' => 'view_item']);

        $this->withSession(['consent.analytics' => true])->get('/products/'.$product->slug)->assertOk();
        $this->assertDatabaseHas('analytics_events', ['event_name' => 'view_item']);
        $this->assertSame($product->id, AnalyticsEvent::query()->where('event_name', 'view_item')->firstOrFail()->payload['product_id']);

        $this->get('/shop?q=hoodie')->assertInertia(fn (Assert $page) => $page->where('seo.indexable', false));
    }

    public function test_store_information_is_centralized_and_consent_choices_are_persisted(): void
    {
        SiteContent::query()->create([
            'key' => 'store_information',
            'value' => ['company_name' => 'RAOZA Commerce B.V.', 'support_email' => 'care@example.test'],
        ]);

        $store = app(StoreInformation::class)->get();
        $this->assertSame('RAOZA Commerce B.V.', $store['company_name']);
        $this->assertSame('care@example.test', $store['support_email']);
        $this->assertSame('Netherlands', $store['country']);

        $this->from('/pages/cookies')->post('/consent', ['analytics' => true, 'marketing' => false])
            ->assertRedirect('/pages/cookies');

        $this->assertTrue((bool) session('consent.analytics'));
        $this->assertFalse((bool) session('consent.marketing'));
        $this->assertSame(1, ConsentRecord::query()->count());
        $this->assertTrue(ConsentRecord::query()->firstOrFail()->analytics);
    }

    public function test_seeded_customer_copy_contains_no_placeholder_or_demo_language(): void
    {
        $this->seed(LocalQaSeeder::class);

        $copy = ContentPage::query()->get()->flatMap(fn (ContentPage $page) => [
            $page->title,
            $page->seo_description,
            json_encode($page->content),
        ])->implode(' ');

        $this->assertDoesNotMatchRegularExpression('/\b(demo|placeholder|test copy|coming later)\b/i', $copy);
    }
}
