<?php

namespace App\Http\Controllers;

use App\Domain\Content\Models\ContentPage;
use App\Domain\Content\Services\SeoService;
use App\Domain\Fulfillment\Services\ShippingQuoteService;
use Inertia\Inertia;
use Inertia\Response;

class ContentController extends Controller
{
    public function __construct(private SeoService $seo) {}

    public function show(string $slug, ShippingQuoteService $shipping): Response
    {
        $page = ContentPage::query()->where('slug', $slug)->firstOrFail();
        abort_unless($page->isPublished(), 404);

        return Inertia::render('Storefront/ContentPage', [
            'page' => $page->only('key', 'title', 'slug', 'content'),
            'shippingMethods' => $page->key === 'shipping' ? $shipping->active() : [],
            'seo' => $this->seo->page($page),
        ]);
    }
}
