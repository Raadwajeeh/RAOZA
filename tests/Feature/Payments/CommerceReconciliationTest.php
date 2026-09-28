<?php

namespace Tests\Feature\Payments;

use App\Domain\Commerce\Enums\PaymentStatus;
use App\Domain\Commerce\Services\CommerceReconciliationService;
use App\Domain\Marketing\Models\AnalyticsEvent;
use App\Domain\Payments\Contracts\PaymentProvider;
use App\Domain\Payments\Services\PaymentService;
use App\Domain\Returns\Enums\RefundStatus;
use App\Domain\Returns\Services\RefundService;
use App\Mail\PaymentConfirmedMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\BuildsCommerceFixtures;
use Tests\Support\FakePaymentProvider;
use Tests\TestCase;

class CommerceReconciliationTest extends TestCase
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

    public function test_delayed_paid_payment_is_reconciled_with_irreversible_side_effects_once(): void
    {
        Mail::fake();
        $fixture = $this->checkoutOrder([['price' => 2500, 'stock' => 3]], 0, 'EUR', ['_analytics_consent' => true]);
        app(PaymentService::class)->createAttempt($fixture['order'], 'https://store.test/return', 'https://store.test/webhook');
        $this->provider->status = 'paid';

        $first = app(CommerceReconciliationService::class)->reconcile(10);
        $second = app(CommerceReconciliationService::class)->reconcile(10);

        $this->assertSame(1, $first['payments_checked']);
        $this->assertSame(1, $first['payments_changed']);
        $this->assertSame(0, $first['failures']);
        $this->assertSame(0, $second['payments_checked']);
        $this->assertSame(PaymentStatus::Paid, $fixture['order']->fresh()->payment_status);
        $this->assertSame(2, $fixture['inventories'][0]->fresh()->quantity_on_hand);
        $this->assertSame(0, $fixture['inventories'][0]->fresh()->quantity_reserved);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertDatabaseCount('payment_events', 1);
        Mail::assertQueued(PaymentConfirmedMail::class, 1);
        $this->assertSame(1, AnalyticsEvent::query()->where('event_name', 'purchase')->where('order_id', $fixture['order']->id)->count());
    }

    public function test_refund_reconciliation_submits_then_synchronizes_and_skips_terminal_refund(): void
    {
        $fixture = $this->checkoutOrder([['price' => 1000, 'stock' => 2]]);
        $payment = app(PaymentService::class)->createAttempt($fixture['order'], 'https://store.test/return', 'https://store.test/webhook');
        $this->provider->status = 'paid';
        app(PaymentService::class)->sync($payment, 'test_paid');
        $refund = app(RefundService::class)->request($fixture['order']->fresh(), 300, idempotencyKey: 'reconcile-refund');
        $this->provider->refundStatus = 'pending';
        $this->provider->refundId = 're_reconcile';

        $submitted = app(CommerceReconciliationService::class)->reconcile(10);
        $this->assertSame(1, $submitted['refunds_checked']);
        $this->assertSame(1, $submitted['refunds_changed']);
        $this->assertSame(RefundStatus::Processing, $refund->fresh()->status);

        $this->provider->refundStatus = 'refunded';
        $completed = app(CommerceReconciliationService::class)->reconcile(10);
        $repeat = app(CommerceReconciliationService::class)->reconcile(10);

        $this->assertSame(1, $completed['refunds_checked']);
        $this->assertSame(1, $completed['refunds_changed']);
        $this->assertSame(0, $repeat['refunds_checked']);
        $this->assertSame(RefundStatus::Succeeded, $refund->fresh()->status);
        $this->assertSame(PaymentStatus::PartiallyRefunded, $fixture['order']->fresh()->payment_status);
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_reconciliation_command_is_bounded_and_safe_to_repeat(): void
    {
        Mail::fake();
        $fixture = $this->checkoutOrder([['price' => 1000, 'stock' => 2]]);
        app(PaymentService::class)->createAttempt($fixture['order'], 'https://store.test/return', 'https://store.test/webhook');
        $this->provider->status = 'paid';

        $this->artisan('commerce:reconcile', ['--limit' => 1])->assertSuccessful();
        $this->artisan('commerce:reconcile', ['--limit' => 1])->assertSuccessful();
        $this->artisan('commerce:reconcile', ['--limit' => 0])->assertExitCode(2);

        $this->assertSame(PaymentStatus::Paid, $fixture['order']->fresh()->payment_status);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertDatabaseCount('payment_events', 1);
        Mail::assertQueued(PaymentConfirmedMail::class, 1);
    }
}
