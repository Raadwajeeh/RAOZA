<?php
namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;
    public function test_storefront_responses_include_baseline_security_headers(): void
    {
        $this->get('/')
            ->assertHeader('X-Content-Type-Options','nosniff')
            ->assertHeader('X-Frame-Options','DENY')
            ->assertHeader('Referrer-Policy','strict-origin-when-cross-origin');
    }
}
