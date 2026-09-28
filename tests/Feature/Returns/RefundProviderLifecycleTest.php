<?php

namespace Tests\Feature\Returns;

use App\Domain\Commerce\Enums\PaymentStatus;
use App\Domain\Payments\Contracts\PaymentProvider;
use App\Domain\Payments\Data\ProviderRefund;
use App\Domain\Payments\Enums\PaymentAttemptStatus;
use App\Domain\Payments\Exceptions\ProviderOperationException;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Services\PaymentService;
use App\Domain\Returns\Enums\RefundStatus;
use App\Domain\Returns\Enums\ReturnStatus;
use App\Domain\Returns\Models\ReturnRequest;
use App\Domain\Returns\Services\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Support\BuildsCommerceFixtures;
use Tests\Support\FakePaymentProvider;
use Tests\TestCase;

class RefundProviderLifecycleTest extends TestCase
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

    public function test_provider_id_and_pending_state_are_persisted_then_synchronized_idempotently(): void
    {
        [$fixture] = $this->paidFixture();
        $service = app(RefundService::class);
        $refund = $service->request($fixture['order']->fresh(), 400, idempotencyKey: 'partial-refund');
        $stockAfterPayment = $fixture['inventories'][0]->fresh()->quantity_on_hand;

        $this->provider->refundId = 're_partial';
        $this->provider->refundStatus = 'pending';
        $submitted = $service->submit($refund);

        $this->assertSame(RefundStatus::Processing, $submitted->status);
        $this->assertSame('re_partial', $submitted->provider_refund_id);
        $this->assertSame('pending', $submitted->provider_status);
        $this->assertSame(PaymentStatus::Paid, $fixture['order']->fresh()->payment_status);

        $this->provider->refundStatus = 'refunded';
        $completed = $service->sync($submitted);
        $again = $service->sync($completed);

        $this->assertSame(RefundStatus::Succeeded, $again->status);
        $this->assertSame(PaymentStatus::PartiallyRefunded, $fixture['order']->fresh()->payment_status);
        $this->assertSame($stockAfterPayment, $fixture['inventories'][0]->fresh()->quantity_on_hand);
        $this->assertSame(0, $fixture['inventories'][0]->fresh()->quantity_reserved);
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_remote_refund_is_recovered_by_stable_reference_before_create(): void
    {
        [$fixture] = $this->paidFixture();
        $service = app(RefundService::class);
        $refund = $service->request($fixture['order']->fresh(), 250, idempotencyKey: 'recover-me');
        $reference = hash('sha256', 'raoza-refund|recover-me');
        $this->provider->remoteRefunds[$reference] = new ProviderRefund(
            're_recovered',
            $this->provider->id,
            'refunded',
            250,
            'EUR',
            now()->toIso8601String(),
            $reference,
        );

        $completed = $service->submit($refund);

        $this->assertSame(RefundStatus::Succeeded, $completed->status);
        $this->assertSame('re_recovered', $completed->provider_refund_id);
        $this->assertSame(1, $this->provider->findRefundCalls);
        $this->assertSame(0, $this->provider->createRefundCalls);
    }

    public function test_recent_processing_claim_prevents_duplicate_submission_and_stale_claim_recovers(): void
    {
        [$fixture] = $this->paidFixture();
        $service = app(RefundService::class);
        $refund = $service->request($fixture['order']->fresh(), 300, idempotencyKey: 'uncertain-refund');
        $this->provider->refundCreateException = new ProviderOperationException('Network outcome unknown.', true);

        try {
            $service->submit($refund);
            $this->fail('The provider exception was not propagated.');
        } catch (ProviderOperationException $exception) {
            $this->assertTrue($exception->outcomeUncertain);
        }

        $processing = $refund->fresh();
        $reference = $processing->metadata['provider_reference'];
        $this->assertSame(RefundStatus::Processing, $processing->status);
        $this->assertNull($processing->provider_refund_id);

        $sameClaim = $service->submit($processing);
        $this->assertSame(RefundStatus::Processing, $sameClaim->status);
        $this->assertSame(1, $this->provider->createRefundCalls);

        $this->travel(6)->minutes();
        $this->provider->refundCreateException = null;
        $this->provider->refundStatus = 'refunded';
        $this->provider->refundId = 're_after_recovery';
        $completed = $service->submit($sameClaim->fresh());

        $this->assertSame(RefundStatus::Succeeded, $completed->status);
        $this->assertSame($reference, $completed->metadata['provider_reference']);
        $this->assertSame(2, $this->provider->createRefundCalls);
    }

    public function test_deterministic_provider_rejection_fails_without_consuming_balance(): void
    {
        [$fixture] = $this->paidFixture();
        $service = app(RefundService::class);
        $refund = $service->request($fixture['order']->fresh(), 600, idempotencyKey: 'rejected-refund');
        $this->provider->refundCreateException = new ProviderOperationException('Rejected.', false);

        try {
            $service->submit($refund);
            $this->fail('The provider rejection was not propagated.');
        } catch (ProviderOperationException $exception) {
            $this->assertFalse($exception->outcomeUncertain);
        }

        $this->assertSame(RefundStatus::Failed, $refund->fresh()->status);
        $this->assertSame(1000, $service->refundableAmount($fixture['order']->fresh()));
        $this->assertSame(PaymentStatus::Paid, $fixture['order']->fresh()->payment_status);
    }

    public function test_provider_amount_currency_and_payment_mismatches_fail_safely(): void
    {
        $cases = [
            'amount' => ['refundAmount' => 499],
            'currency' => ['refundCurrency' => 'USD'],
            'payment' => ['refundPaymentId' => 'tr_wrong'],
        ];

        foreach ($cases as $name => $changes) {
            [$fixture] = $this->paidFixture('tr_'.$name);
            $service = app(RefundService::class);
            $this->provider->refundId = 're_'.$name;
            $this->provider->refundStatus = 'pending';
            $refund = $service->submit($service->request($fixture['order']->fresh(), 500, idempotencyKey: 'mismatch-'.$name));
            foreach ($changes as $property => $value) {
                $this->provider->{$property} = $value;
            }

            try {
                $service->sync($refund);
                $this->fail("The {$name} mismatch was accepted.");
            } catch (RuntimeException $exception) {
                $this->assertSame('Provider refund does not match the internal refund.', $exception->getMessage());
            }

            $this->assertSame(RefundStatus::Processing, $refund->fresh()->status);
            $this->assertSame(PaymentStatus::Paid, $fixture['order']->fresh()->payment_status);
            $this->provider->refundAmount = null;
            $this->provider->refundCurrency = null;
            $this->provider->refundPaymentId = null;
        }
    }

    public function test_unknown_provider_refund_status_never_becomes_success(): void
    {
        [$fixture] = $this->paidFixture();
        $service = app(RefundService::class);
        $this->provider->refundStatus = 'pending';
        $refund = $service->submit($service->request($fixture['order']->fresh(), 500, idempotencyKey: 'unknown-status'));
        $this->provider->refundStatus = 'mystery';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported provider refund status.');
        try {
            $service->sync($refund);
        } finally {
            $this->assertSame(RefundStatus::Processing, $refund->fresh()->status);
            $this->assertSame(PaymentStatus::Paid, $fixture['order']->fresh()->payment_status);
        }
    }

    public function test_refund_selects_exactly_one_authoritative_paid_payment(): void
    {
        [$fixture, $paid] = $this->paidFixture();
        $failed = Payment::query()->create([
            'order_id' => $fixture['order']->id,
            'provider' => 'mollie',
            'provider_payment_id' => 'tr_failed_attempt',
            'currency' => 'EUR',
            'amount' => 1000,
            'status' => PaymentAttemptStatus::Failed,
        ]);

        $refund = app(RefundService::class)->request($fixture['order']->fresh(), 100, idempotencyKey: 'paid-attempt-only');
        $this->assertSame($paid->id, $refund->payment_id);
        $this->assertNotSame($failed->id, $refund->payment_id);

        Payment::query()->create([
            'order_id' => $fixture['order']->id,
            'provider' => 'mollie',
            'provider_payment_id' => 'tr_second_paid',
            'currency' => 'EUR',
            'amount' => 1000,
            'status' => PaymentAttemptStatus::Paid,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('exactly one authoritative paid provider payment');
        app(RefundService::class)->request($fixture['order']->fresh(), 100, idempotencyKey: 'ambiguous-paid-attempts');
    }

    public function test_idempotency_key_cannot_be_reused_for_another_amount_order_or_return(): void
    {
        [$fixture] = $this->paidFixture();
        $return = ReturnRequest::query()->create([
            'return_number' => 'RT-TEST-1',
            'order_id' => $fixture['order']->id,
            'status' => ReturnStatus::Inspected,
            'requested_at' => now(),
        ]);
        $service = app(RefundService::class);
        $service->request($fixture['order']->fresh(), 100, $return, idempotencyKey: 'one-purpose');

        foreach ([
            fn () => $service->request($fixture['order']->fresh(), 101, $return, idempotencyKey: 'one-purpose'),
            fn () => $service->request($fixture['order']->fresh(), 100, null, idempotencyKey: 'one-purpose'),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('The idempotency key was reused for a different refund request.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('different request', $exception->getMessage());
            }
        }

        [$otherFixture] = $this->paidFixture('tr_other_order');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('different request');
        $service->request($otherFixture['order']->fresh(), 100, idempotencyKey: 'one-purpose');
    }

    private function paidFixture(string $providerPaymentId = 'tr_paid'): array
    {
        $this->provider->id = $providerPaymentId;
        $this->provider->status = 'open';
        $fixture = $this->checkoutOrder([['price' => 1000, 'stock' => 2]]);
        $payment = app(PaymentService::class)->createAttempt($fixture['order'], 'https://store.test/return', 'https://store.test/webhook');
        $this->provider->status = 'paid';
        app(PaymentService::class)->sync($payment, 'test_paid');

        return [$fixture, $payment];
    }
}
