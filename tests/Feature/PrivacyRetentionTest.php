<?php

namespace Tests\Feature;

use App\Domain\Privacy\Services\RetentionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class PrivacyRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_is_dry_run_and_uses_only_explicit_periods(): void
    {
        config()->set('retention.categories.analytics_events.days', 30);
        DB::table('analytics_events')->insert([
            ['event_id'=>Str::uuid(),'event_name'=>'page_view','occurred_at'=>now()->subDays(31),'created_at'=>now()->subDays(31),'updated_at'=>now()->subDays(31)],
            ['event_id'=>Str::uuid(),'event_name'=>'page_view','occurred_at'=>now()->subDays(2),'created_at'=>now()->subDays(2),'updated_at'=>now()->subDays(2)],
        ]);

        $report = app(RetentionService::class)->report();

        $this->assertSame(1, $report['analytics_events']['eligible']);
        $this->assertFalse($report['sessions']['configured']);
        $this->assertDatabaseCount('analytics_events', 2);
        $this->artisan('privacy:retention-report')->assertSuccessful();
        $this->assertDatabaseCount('analytics_events', 2);
    }

    public function test_pruning_requires_policy_enablement_and_confirmation(): void
    {
        config()->set('retention.categories.sessions.days', 30);
        DB::table('sessions')->insert(['id'=>'stale','payload'=>'x','last_activity'=>now()->subDays(31)->timestamp]);
        $service = app(RetentionService::class);

        $this->expectException(RuntimeException::class);
        try {
            $service->prune(true);
        } finally {
            config()->set('retention.pruning_enabled', true);
            try {
                $service->prune(false);
                $this->fail('Explicit confirmation was not required.');
            } catch (RuntimeException) {
                $deleted = $service->prune(true);
                $this->assertSame(1, $deleted['sessions']);
                $this->assertDatabaseMissing('sessions', ['id'=>'stale']);
            }
        }
    }
}
