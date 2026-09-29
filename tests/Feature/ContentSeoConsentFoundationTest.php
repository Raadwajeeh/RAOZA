<?php

namespace Tests\Feature;

use Tests\TestCase;

class ContentSeoConsentFoundationTest extends TestCase
{
    public function test_public_content_consent_and_discovery_routes_exist(): void
    {
        $routeNames = collect(app('router')->getRoutes())->pluck('action.as');

        foreach (['content.show', 'consent.store', 'sitemap', 'robots'] as $routeName) {
            $this->assertTrue($routeNames->contains($routeName));
        }
    }

    public function test_foundation_files_exist(): void
    {
        $this->assertFileExists(app_path('Domain/Content/Services/SeoService.php'));
        $this->assertFileExists(app_path('Mail/PaymentConfirmedMail.php'));
        $this->assertFileExists(resource_path('js/Components/ConsentBanner.tsx'));
    }
}
