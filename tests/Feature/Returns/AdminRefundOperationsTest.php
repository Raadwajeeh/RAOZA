<?php

namespace Tests\Feature\Returns;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Payments\Contracts\PaymentProvider;
use App\Domain\Payments\Services\PaymentService;
use App\Domain\Returns\Enums\RefundStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCommerceFixtures;
use Tests\Support\FakePaymentProvider;
use Tests\TestCase;

class AdminRefundOperationsTest extends TestCase
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

    public function test_refund_actions_require_authenticated_refund_permission(): void
    {
        $order = $this->paidOrder();

        $this->post(route('admin.refunds.store', $order), ['amount' => 100])
            ->assertRedirect(route('login'));

        $support = User::factory()->create(['role' => AdminRole::Support, 'is_admin' => false]);
        $this->actingAs($support)
            ->post(route('admin.refunds.store', $order), ['amount' => 100])
            ->assertForbidden();

        $this->assertDatabaseCount('refunds', 0);
        $this->assertSame(0, $this->provider->createRefundCalls);
    }

    public function test_duplicate_admin_submission_reuses_internal_and_provider_refund(): void
    {
        $order = $this->paidOrder();
        $manager = User::factory()->create(['role' => AdminRole::OrderManager, 'is_admin' => false]);
        $this->provider->refundStatus = 'pending';
        $this->provider->refundId = 're_admin';
        $payload = ['amount' => 400, 'reason' => 'Approved correction', 'idempotency_key' => 'admin-stable-key'];

        $this->actingAs($manager)
            ->post(route('admin.refunds.store', $order), ['amount' => 400])
            ->assertSessionHasErrors('idempotency_key');
        $this->actingAs($manager)->post(route('admin.refunds.store', $order), $payload)->assertRedirect();
        $this->actingAs($manager)->post(route('admin.refunds.store', $order), $payload)->assertRedirect();

        $this->assertDatabaseCount('refunds', 1);
        $this->assertDatabaseHas('refunds', [
            'order_id' => $order->id,
            'provider_refund_id' => 're_admin',
            'status' => RefundStatus::Processing->value,
            'idempotency_key' => 'admin-stable-key',
        ]);
        $this->assertSame(1, $this->provider->createRefundCalls);
        $this->assertSame(1, $this->provider->fetchRefundCalls);
    }

    private function paidOrder(): \App\Domain\Commerce\Models\Order
    {
        $fixture = $this->checkoutOrder([['price' => 1000, 'stock' => 2]]);
        $payment = app(PaymentService::class)->createAttempt($fixture['order'], 'https://store.test/return', 'https://store.test/webhook');
        $this->provider->status = 'paid';
        app(PaymentService::class)->sync($payment, 'test_paid');

        return $fixture['order']->fresh();
    }
}
