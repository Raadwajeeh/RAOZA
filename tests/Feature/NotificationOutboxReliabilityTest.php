<?php

namespace Tests\Feature;

use App\Jobs\DeliverTransactionalNotification;
use App\Mail\OrderReceivedMail;
use App\Models\NotificationDispatch;
use App\Services\TransactionalNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\Support\BuildsCommerceFixtures;
use Tests\TestCase;

final class NotificationOutboxReliabilityTest extends TestCase
{
    use BuildsCommerceFixtures, RefreshDatabase;

    public function test_pending_outbox_survives_an_unprocessed_queue_and_keeps_payload_encrypted(): void
    {
        Queue::fake();
        $order=$this->checkoutOrder()['order'];
        app(TransactionalNotificationService::class)->queueOnce('outbox:'.$order->id,$order->customer_email,new OrderReceivedMail($order),$order);
        Queue::assertPushed(DeliverTransactionalNotification::class,1);
        $dispatch=NotificationDispatch::firstOrFail();
        $this->assertSame('pending',$dispatch->status);
        $this->assertStringNotContainsString($order->customer_email,$dispatch->getRawOriginal('encrypted_recipient'));
        $this->assertStringNotContainsString($order->customer_email,$dispatch->getRawOriginal('encrypted_mailable'));
    }

    public function test_transport_failure_releases_claim_for_bounded_retry_without_losing_outbox(): void
    {
        Queue::fake();
        $order=$this->checkoutOrder()['order'];
        app(TransactionalNotificationService::class)->queueOnce('outbox-fail:'.$order->id,$order->customer_email,new OrderReceivedMail($order),$order);
        $dispatch=NotificationDispatch::firstOrFail();
        Mail::shouldReceive('to')->once()->andReturnSelf();
        Mail::shouldReceive('sendNow')->once()->andThrow(new RuntimeException('simulated transport outage'));
        try {(new DeliverTransactionalNotification($dispatch->id))->handle();$this->fail('Expected transport failure.');}
        catch(RuntimeException $e){$this->assertSame('simulated transport outage',$e->getMessage());}
        $dispatch->refresh();
        $this->assertSame('pending',$dispatch->status);
        $this->assertSame(RuntimeException::class,$dispatch->last_error_type);
        $this->assertNotNull($dispatch->next_attempt_at);
        $this->assertSame(1,$dispatch->attempts);
    }

    public function test_recovered_delivery_is_idempotent_after_sent_marker(): void
    {
        Queue::fake(); Mail::fake();
        $order=$this->checkoutOrder()['order'];
        app(TransactionalNotificationService::class)->queueOnce('outbox-recover:'.$order->id,$order->customer_email,new OrderReceivedMail($order),$order);
        $dispatch=NotificationDispatch::firstOrFail();
        $job=new DeliverTransactionalNotification($dispatch->id);
        $job->handle(); $job->handle();
        Mail::assertSent(OrderReceivedMail::class,1);
        $this->assertSame('sent',$dispatch->fresh()->status);
        $this->assertNotNull($dispatch->fresh()->sent_at);
    }
}
