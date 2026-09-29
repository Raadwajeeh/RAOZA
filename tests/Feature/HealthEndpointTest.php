<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
}
