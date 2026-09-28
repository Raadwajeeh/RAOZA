<?php

namespace Tests\Feature\Returns;

use App\Domain\Commerce\Enums\PaymentStatus;
use App\Domain\Payments\Contracts\PaymentProvider;
use App\Domain\Payments\Services\PaymentService;
use App\Domain\Returns\Enums\RefundStatus;
use App\Domain\Returns\Services\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Support\BuildsCommerceFixtures;
use Tests\Support\FakePaymentProvider;
use Tests\TestCase;

class RefundIntegrityTest extends TestCase
{
    use BuildsCommerceFixtures;
    use RefreshDatabase;

    private function paidFixture(): array
    {
        $provider = new FakePaymentProvider;
        $this->app->instance(PaymentProvider::class, $provider);
        $fixture = $this->checkoutOrder([['price' => 1000, 'stock' => 2]]);
        $payment = app(PaymentService::class)->createAttempt($fixture['order'], 'https://store.test/return', 'https://store.test/webhook');
        $provider->status = 'paid';
        app(PaymentService::class)->sync($payment, 'webhook');

        return [$fixture, $payment, $provider];
    }

    public function test_pending_and_succeeded_refunds_cannot_cumulatively_exceed_payment(): void
    {
        [$fixture,,$provider] = $this->paidFixture();
        $service = app(RefundService::class);
        $first = $service->request($fixture['order']->fresh(), 600, idempotencyKey: 'refund-one');

        try {
            $service->request($fixture['order']->fresh(), 401, idempotencyKey: 'refund-too-large');
            $this->fail('Cumulative refund limit was exceeded.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('remaining refundable amount', $exception->getMessage());
        }

        $provider->refundStatus = 'refunded';
        $provider->refundId = 're_first';
        $service->submit($first);
        $second = $service->request($fixture['order']->fresh(), 400, idempotencyKey: 'refund-two');
        $provider->refundId = 're_second';
        $service->submit($second);

        $sameCompletedRequest = $service->request($fixture['order']->fresh(), 400, idempotencyKey: 'refund-two');

        $this->assertSame(PaymentStatus::Refunded, $fixture['order']->fresh()->payment_status);
        $this->assertSame($second->id, $sameCompletedRequest->id);
        $this->assertSame(1000, $fixture['order']->refunds()->where('status', RefundStatus::Succeeded->value)->sum('amount'));
        $this->assertSame(1, $fixture['inventories'][0]->fresh()->quantity_on_hand);
        $this->assertSame(0, $fixture['inventories'][0]->fresh()->quantity_reserved);
    }

    public function test_refund_idempotency_and_failed_transition_are_safe(): void
    {
        [$fixture,,$provider] = $this->paidFixture();
        $service = app(RefundService::class);
        $first = $service->request($fixture['order']->fresh(), 500, idempotencyKey: 'stable-key');
        $again = $service->request($fixture['order']->fresh(), 500, idempotencyKey: 'stable-key');

        $this->assertSame($first->id, $again->id);
        $provider->refundStatus = 'failed';
        $failed = $service->submit($first);
        $failedAgain = $service->submit($failed);
        $this->assertSame($failed->id, $failedAgain->id);
        $this->assertSame(RefundStatus::Failed, $failedAgain->status);

        $this->assertSame(PaymentStatus::Paid, $fixture['order']->fresh()->payment_status);
        $this->assertSame(1000, $service->refundableAmount($fixture['order']->fresh()));
    }
}
