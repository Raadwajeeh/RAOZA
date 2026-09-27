<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
