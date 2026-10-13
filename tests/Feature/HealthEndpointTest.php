<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

final class HealthEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_liveness_endpoint_does_not_expose_internal_details(): void
    {
        $this->get('/health/live')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonMissing(['exception', 'password', 'database']);
    }

    public function test_readiness_endpoint_reports_database_ready(): void
    {
        $this->get('/health/ready')
            ->assertOk()
            ->assertJsonPath('status', 'ready')
            ->assertJsonPath('checks.database', true);
    }

    public function test_readiness_fails_closed_without_leaking_database_exception(): void
    {
        DB::shouldReceive('select')->once()->andThrow(new RuntimeException('secret-host internal path'));

        $this->get('/health/ready')
            ->assertStatus(503)
            ->assertJson([
                'status' => 'not_ready',
                'checks' => ['database' => false],
            ])
            ->assertDontSee('secret-host')
            ->assertDontSee('internal path');
    }

    public function test_repeated_health_probes_are_read_only_and_do_not_start_storefront_sessions(): void
    {
        $tables = ['carts', 'analytics_events', 'consent_records', 'sessions'];
        $before = collect($tables)->mapWithKeys(fn (string $table) => [$table => Schema::hasTable($table) ? DB::table($table)->count() : 0]);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->get('/health/live')->assertOk()->assertHeaderMissing('Set-Cookie');
            $this->get('/health/ready')->assertOk()->assertHeaderMissing('Set-Cookie');
        }

        foreach ($before as $table => $count) {
            $this->assertSame($count, DB::table($table)->count(), "Health probes mutated {$table}.");
        }
    }

    public function test_forwarded_https_is_not_trusted_without_an_explicit_proxy_allowlist(): void
    {
        Route::get('/_test/request-security', fn () => ['secure' => request()->isSecure()]);

        $this->withServerVariables(['HTTP_X_FORWARDED_PROTO' => 'https'])
            ->get('http://localhost/_test/request-security')
            ->assertOk()
            ->assertJsonPath('secure', false);
    }
}
