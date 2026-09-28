<?php

namespace Tests\Feature\Payments;

use App\Domain\Commerce\Enums\PaymentStatus;
use App\Domain\Marketing\Models\AnalyticsEvent;
use App\Domain\Payments\Contracts\PaymentProvider;
use App\Domain\Payments\Enums\PaymentAttemptStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentEvent;
use App\Domain\Payments\Services\PaymentService;
use App\Domain\Returns\Services\RefundService;
use App\Mail\PaymentConfirmedMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\Support\BuildsCommerceFixtures;
use Tests\Support\FakePaymentProvider;
use Tests\TestCase;

class PaymentLifecycleIntegrityTest extends TestCase
{
    use BuildsCommerceFixtures;
    use RefreshDatabase;

    private FakePaymentProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new FakePaymentProvider;
        $this->app->instance(PaymentProvider::class, $this->provider);
    }

    public function test_repeated_payment_start_reuses_one_active_attempt(): void
    {
        $fixture = $this->checkoutOrder();
        $service = app(PaymentService::class);

        $first = $service->createAttempt($fixture['order'], 'https://store.test/return', 'https://store.test/webhook');
        $second = $service->createAttempt($fixture['order']->fresh(), 'https://store.test/return', 'https://store.test/webhook');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $this->provider->createCalls);
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(1, $fixture['inventories'][0]->fresh()->quantity_reserved);
    }

    public function test_duplicate_paid_sync_commits_inventory_exactly_once(): void
    {
        $fixture = $this->checkoutOrder([['price' => 2500, 'quantity' => 2, 'stock' => 5]]);
        $service = app(PaymentService::class);
        $payment = $service->createAttempt($fixture['order'], 'https://store.test/return', 'https://store.test/webhook');
        $this->provider->status = 'paid';

        $first = $service->sync($payment, 'webhook');
        $second = $service->sync($payment->fresh(), 'customer_return');

        $this->assertTrue($first->becamePaid);
        $this->assertFalse($second->becamePaid);
        $this->assertFalse($second->eventProcessed);
        $this->assertSame(PaymentStatus::Paid, $fixture['order']->fresh()->payment_status);
        $this->assertSame(3, $fixture['inventories'][0]->fresh()->quantity_on_hand);
        $this->assertSame(0, $fixture['inventories'][0]->fresh()->quantity_reserved);
        $this->assertDatabaseCount('payment_events', 1);
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_webhook_and_customer_return_emit_paid_side_effects_once(): void
    {
        Mail::fake();
        $fixture = $this->checkoutOrder([['price' => 2500]], 0, 'EUR', ['_analytics_consent' => true]);
        $payment = app(PaymentService::class)->createAttempt($fixture['order'], 'https://store.test/return', 'https://store.test/webhook');
        $this->provider->status = 'paid';

        $this->post(route('payments.webhook'), ['id' => $payment->provider_payment_id])->assertOk();
        $this->withSession(['last_order_number' => $fixture['order']->order_number])
            ->get(route('payments.return', $fixture['order']->order_number))
            ->assertRedirect(route('checkout.confirmation', $fixture['order']->order_number));

        Mail::assertQueued(PaymentConfirmedMail::class, 1);
        $this->assertSame(1, AnalyticsEvent::query()->where('event_name', 'purchase')->where('order_id', $fixture['order']->id)->count());
        $this->assertDatabaseCount('payment_events', 1);
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_terminal_failures_release_reservation_exactly_once(): void
    {
        foreach (['failed', 'canceled', 'expired'] as $index => $status) {
            $this->provider->id = 'tr_terminal_'.$index;
            $this->provider->status = 'open';
            $fixture = $this->checkoutOrder([['price' => 1000, 'stock' => 2]]);
            $payment = app(PaymentService::class)->createAttempt($fixture['order'], 'https://store.test/return', 'https://store.test/webhook');
            $this->provider->status = $status;

            app(PaymentService::class)->sync($payment, 'webhook');
            app(PaymentService::class)->sync($payment->fresh(), 'customer_return');

            $this->assertSame(0, $fixture['inventories'][0]->fresh()->quantity_reserved);
            $this->assertSame(2, $fixture['inventories'][0]->fresh()->quantity_on_hand);
            $this->assertSame(PaymentStatus::Failed, $fixture['order']->fresh()->payment_status);
        }
    }

    public function test_failed_payment_can_retry_only_if_stock_can_be_reserved_again(): void
    {
        $fixture = $this->checkoutOrder([['price' => 1000, 'stock' => 1]]);
        $service = app(PaymentService::class);
        $payment = $service->createAttempt($fixture['order'], 'https://store.test/return', 'https://store.test/webhook');
        $this->provider->status = 'failed';
        $service->sync($payment, 'webhook');

        $this->provider->id = 'tr_retry';
        $this->provider->status = 'open';
        $retry = $service->createAttempt($fixture['order']->fresh(), 'https://store.test/return', 'https://store.test/webhook');

        $this->assertNotSame($payment->id, $retry->id);
        $this->assertSame(1, $fixture['inventories'][0]->fresh()->quantity_reserved);

        $this->provider->status = 'paid';
        $service->sync($retry, 'webhook');
        $this->assertSame(0, $fixture['inventories'][0]->fresh()->quantity_on_hand);
        $this->assertSame(0, $fixture['inventories'][0]->fresh()->quantity_reserved);
    }

    public function test_failed_payment_retry_rejects_stock_consumed_elsewhere(): void
    {
        $fixture = $this->checkoutOrder([['price' => 1000, 'stock' => 1]]);
        $service = app(PaymentService::class);
        $payment = $service->createAttempt($fixture['order'], 'https://store.test/return', 'https://store.test/webhook');
        $this->provider->status = 'failed';
        $service->sync($payment, 'webhook');
        $fixture['inventories'][0]->update(['quantity_on_hand' => 0]);
        $this->provider->id = 'tr_retry_without_stock';
        $this->provider->status = 'open';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Stock is no longer available');
        $service->createAttempt($fixture['order']->fresh(), 'https://store.test/return', 'https://store.test/webhook');
    }

    public function test_paid_and_refunded_orders_cannot_start_or_recommit_payment(): void
    {
        $fixture = $this->checkoutOrder([['price' => 1000, 'stock' => 2]]);
        $service = app(PaymentService::class);
        $payment = $service->createAttempt($fixture['order'], 'https://store.test/return', 'https://store.test/webhook');
        $this->provider->status = 'paid';
        $service->sync($payment, 'webhook');

        $this->expectException(RuntimeException::class);
        try {
            $service->createAttempt($fixture['order']->fresh(), 'https://store.test/return', 'https://store.test/webhook');
        } finally {
            $this->provider->refundStatus = 'refunded';
            $this->provider->refundId = 're_test';
            $refund = app(RefundService::class)->request($fixture['order']->fresh(), 1000, idempotencyKey: 'full-refund');
            app(RefundService::class)->submit($refund);
            PaymentEvent::query()->delete(); // Simulates an event written by the pre-hardening key format.

            $result = $service->sync($payment->fresh(), 'customer_return');
            $this->assertFalse($result->becamePaid);
            $this->assertSame(PaymentStatus::Refunded, $fixture['order']->fresh()->payment_status);
            $this->assertSame(1, $fixture['inventories'][0]->fresh()->quantity_on_hand);
            $this->assertDatabaseCount('inventory_movements', 1);
        }
    }

    public function test_stale_failure_cannot_regress_a_paid_attempt_or_order(): void
    {
        $fixture = $this->checkoutOrder([['price' => 1000, 'stock' => 2]]);
        $service = app(PaymentService::class);
        $payment = $service->createAttempt($fixture['order'], 'https://store.test/return', 'https://store.test/webhook');
        $this->provider->status = 'paid';
        $service->sync($payment, 'webhook');
        $this->provider->status = 'failed';

        $service->sync($payment->fresh(), 'provider_sync');

        $this->assertSame(PaymentAttemptStatus::Paid, $payment->fresh()->status);
        $this->assertNull($payment->fresh()->failed_at);
        $this->assertSame(PaymentStatus::Paid, $fixture['order']->fresh()->payment_status);
        $this->assertSame(1, $fixture['inventories'][0]->fresh()->quantity_on_hand);
    }

    public function test_unknown_or_mismatched_provider_state_fails_closed(): void
    {
        $fixture = $this->checkoutOrder();
        $service = app(PaymentService::class);
        $payment = $service->createAttempt($fixture['order'], 'https://store.test/return', 'https://store.test/webhook');
        $this->provider->status = 'mystery';

        try {
            $service->sync($payment, 'webhook');
            $this->fail('Unknown provider state was accepted.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Unsupported provider payment status.', $exception->getMessage());
        }

        $this->provider->status = 'paid';
        $this->provider->amount = $fixture['order']->total_amount + 1;
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not match');
        $service->sync($payment, 'webhook');
    }

    public function test_authorized_is_a_deliberate_pending_state(): void
    {
        $fixture = $this->checkoutOrder();
        $service = app(PaymentService::class);
        $payment = $service->createAttempt($fixture['order'], 'https://store.test/return', 'https://store.test/webhook');
        $this->provider->status = 'authorized';

        $service->sync($payment, 'webhook');

        $this->assertSame(PaymentAttemptStatus::Authorized, $payment->fresh()->status);
        $this->assertSame(PaymentStatus::Pending, $fixture['order']->fresh()->payment_status);
        $this->assertSame(1, $fixture['inventories'][0]->fresh()->quantity_reserved);
    }
}
