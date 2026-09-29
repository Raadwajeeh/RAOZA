<?php

namespace Tests\Feature;

use App\Domain\Commerce\Models\Order;
use App\Domain\Fulfillment\Models\Shipment;
use App\Domain\Returns\Models\Refund;
use App\Mail\OrderReceivedMail;
use App\Mail\OrderShippedMail;
use App\Mail\PaymentConfirmedMail;
use App\Mail\RefundSucceededMail;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class InfrastructureReliabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_transactional_mail_is_queued_after_commit_with_bounded_retries(): void
    {
        $order = new Order;
        $mailables = [
            new OrderReceivedMail($order),
            new PaymentConfirmedMail($order),
            new OrderShippedMail($order, new Shipment),
            new RefundSucceededMail($order, new Refund),
        ];

        foreach ($mailables as $mailable) {
            $this->assertInstanceOf(ShouldQueueAfterCommit::class, $mailable);
            $this->assertSame(3, $mailable->tries);
            $this->assertSame(30, $mailable->timeout);
            $this->assertSame([30, 120], $mailable->backoff());
        }
    }

    public function test_database_queue_commits_before_dispatch_and_records_failures(): void
    {
        $this->assertSame('sync', config('queue.default'), 'PHPUnit must remain isolated from the production queue.');
        $this->assertSame('database', config('queue.connections.database.driver'));
        $this->assertTrue(config('queue.connections.database.after_commit'));
        $this->assertSame('database-uuids', config('queue.failed.driver'));
        $this->assertTrue(Schema::hasTable('jobs'));
        $this->assertTrue(Schema::hasTable('job_batches'));
        $this->assertTrue(Schema::hasTable('failed_jobs'));
    }

    public function test_reconciliation_is_scheduled_every_five_minutes_without_overlap(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains($event->command, 'commerce:reconcile --limit=50'));

        $this->assertNotNull($event);
        $this->assertSame('*/5 * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertSame(10, $event->expiresAt);
    }
}
