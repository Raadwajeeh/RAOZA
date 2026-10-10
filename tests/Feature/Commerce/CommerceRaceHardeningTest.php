<?php

namespace Tests\Feature\Commerce;

use App\Domain\Commerce\Enums\CartStatus;
use App\Domain\Commerce\Enums\OrderStatus;
use App\Domain\Commerce\Enums\PaymentStatus;
use App\Domain\Commerce\Models\Cart;
use App\Domain\Commerce\Services\CheckoutService;
use App\Domain\Commerce\Services\OrderCancellationService;
use App\Domain\Commerce\Services\PendingOrderExpirationService;
use App\Domain\Marketing\Models\Discount;
use App\Domain\Marketing\Services\DiscountService;
use App\Domain\Payments\Contracts\PaymentProvider;
use App\Domain\Payments\Enums\PaymentAttemptStatus;
use App\Domain\Payments\Exceptions\ProviderOperationException;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\Support\BuildsCommerceFixtures;
use Tests\Support\FakePaymentProvider;
use Tests\TestCase;

class CommerceRaceHardeningTest extends TestCase
{
    use BuildsCommerceFixtures, RefreshDatabase;

    private FakePaymentProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new FakePaymentProvider;
        $this->app->instance(PaymentProvider::class, $this->provider);
    }

    public function test_expiry_cannot_release_inventory_while_provider_creation_is_in_flight(): void
    {
        $fixture = $this->checkoutOrder();
        $fixture['order']->update(['placed_at' => now()->subHour()]);
        $this->provider->beforePaymentCreate = function () use ($fixture): void {
            $payment = $fixture['order']->payments()->firstOrFail();
            $this->assertSame(PaymentAttemptStatus::Creating, $payment->status);
            $this->assertSame(0, app(PendingOrderExpirationService::class)->expire()['expired']);
            $this->assertSame(1, $fixture['inventories'][0]->fresh()->quantity_reserved);
        };

        $payment = app(PaymentService::class)->createAttempt($fixture['order'], 'https://store.test/return', 'https://store.test/webhook');
        $this->assertNotNull($payment->provider_payment_id);
        $this->assertSame('reserved', $fixture['order']->items()->firstOrFail()->reservation_status);
    }

    public function test_delayed_provider_failure_remains_protected_only_when_outcome_is_uncertain(): void
    {
        foreach ([true, false] as $uncertain) {
            $fixture = $this->checkoutOrder();
            $fixture['order']->update(['placed_at' => now()->subHour()]);
            $this->provider->id = 'tr_failure_'.(int) $uncertain;
            $this->provider->paymentCreateException = new ProviderOperationException('Injected failure.', $uncertain);
            $this->provider->beforePaymentCreate = function () use ($fixture): void {
                $this->assertSame(0, app(PendingOrderExpirationService::class)->expire()['expired']);
                $this->assertSame(1, $fixture['inventories'][0]->fresh()->quantity_reserved);
            };

            try {
                app(PaymentService::class)->createAttempt($fixture['order'], 'https://store.test/return', 'https://store.test/webhook');
                $this->fail('Injected provider failure was not propagated.');
            } catch (ProviderOperationException) {
                $payment = $fixture['order']->payments()->firstOrFail();
                $this->assertSame($uncertain ? PaymentAttemptStatus::Created : PaymentAttemptStatus::Failed, $payment->status);
                $this->assertSame($uncertain ? 0 : 1, app(PendingOrderExpirationService::class)->expire()['expired']);
            }
            $this->provider->paymentCreateException = null;
            $this->provider->beforePaymentCreate = null;
        }
    }

    public function test_paid_provider_result_after_attempted_expiry_commits_owned_inventory_once(): void
    {
        $fixture = $this->checkoutOrder([['price' => 1000, 'stock' => 2]]);
        $fixture['order']->update(['placed_at' => now()->subHour()]);
        $this->provider->status = 'paid';
        $this->provider->beforePaymentCreate = function (): void {
            $this->assertSame(0, app(PendingOrderExpirationService::class)->expire()['expired']);
        };

        $payment = app(PaymentService::class)->createAttempt($fixture['order'], 'https://store.test/return', 'https://store.test/webhook');
        app(PaymentService::class)->sync($payment, 'paid_after_expiry_attempt');
        app(PaymentService::class)->sync($payment->fresh(), 'duplicate_paid');

        $this->assertSame(PaymentStatus::Paid, $fixture['order']->fresh()->payment_status);
        $this->assertSame(1, $fixture['inventories'][0]->fresh()->quantity_on_hand);
        $this->assertSame(0, $fixture['inventories'][0]->fresh()->quantity_reserved);
        $this->assertSame('committed', $fixture['order']->items()->firstOrFail()->reservation_status);
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_two_customers_cannot_reserve_the_same_final_sku(): void
    {
        $first = $this->checkoutOrder([['price' => 1000, 'stock' => 1]]);
        $secondCart = Cart::query()->create(['token' => (string) Str::uuid(), 'status' => CartStatus::Active, 'currency' => 'EUR', 'expires_at' => now()->addDay()]);
        $secondCart->items()->create(['variant_id' => $first['variants'][0]->id, 'quantity' => 1]);

        try {
            app(CheckoutService::class)->createOrder($secondCart, $this->checkoutData($first['shipping']->id, ['email' => 'second@example.com']));
            $this->fail('The final SKU was reserved twice.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('cart', $exception->errors());
        }

        $this->assertSame(1, $first['inventories'][0]->fresh()->quantity_reserved);
        $this->assertSame('reserved', $first['order']->items()->firstOrFail()->reservation_status);
    }

    public function test_paid_payment_cannot_consume_another_orders_reservation(): void
    {
        $first = $this->checkoutOrder([['price' => 1000, 'stock' => 2]]);
        $payment = app(PaymentService::class)->createAttempt($first['order'], 'https://store.test/return', 'https://store.test/webhook');
        $this->provider->status = 'failed';
        app(PaymentService::class)->sync($payment, 'failed');
        $secondCart = Cart::query()->create(['token' => (string) Str::uuid(), 'status' => CartStatus::Active, 'currency' => 'EUR', 'expires_at' => now()->addDay()]);
        $secondCart->items()->create(['variant_id' => $first['variants'][0]->id, 'quantity' => 1]);
        $secondOrder = app(CheckoutService::class)->createOrder($secondCart, $this->checkoutData($first['shipping']->id, ['email' => 'second@example.com']));
        $secondReserved = $first['inventories'][0]->fresh()->quantity_reserved;
        $this->provider->status = 'paid';

        try {
            app(PaymentService::class)->sync($payment->fresh(), 'late_paid');
            $this->fail('A released reservation was recommitted.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('does not own', $exception->getMessage());
        }

        $this->assertSame($secondReserved, $first['inventories'][0]->fresh()->quantity_reserved);
        $this->assertSame('reserved', $secondOrder->items()->firstOrFail()->reservation_status);
        $this->assertSame(PaymentStatus::Failed, $first['order']->fresh()->payment_status);
    }

    public function test_cancellation_rejects_open_and_uncertain_attempts_but_allows_terminal_failure(): void
    {
        $open = $this->checkoutOrder();
        app(PaymentService::class)->createAttempt($open['order'], 'https://store.test/return', 'https://store.test/webhook');
        $this->assertCancellationRejected($open);

        $uncertain = $this->checkoutOrder();
        $this->provider->id = 'tr_uncertain';
        $this->provider->paymentCreateException = new ProviderOperationException('Lost response.', true);
        try {
            app(PaymentService::class)->createAttempt($uncertain['order'], 'https://store.test/return', 'https://store.test/webhook');
        } catch (ProviderOperationException) {
        }
        $this->assertCancellationRejected($uncertain);

        $failed = $this->checkoutOrder();
        $this->provider->paymentCreateException = null;
        $this->provider->id = 'tr_terminal';
        $terminal = app(PaymentService::class)->createAttempt($failed['order'], 'https://store.test/return', 'https://store.test/webhook');
        $this->provider->status = 'failed';
        app(PaymentService::class)->sync($terminal, 'failed');
        $this->assertSame(OrderStatus::Cancelled, $failed['order']->fresh()->order_status);
    }

    public function test_cancelled_order_never_reuses_an_active_unreserved_attempt(): void
    {
        $fixture = $this->checkoutOrder();
        $fixture['order']->items()->update(['reservation_status' => 'released']);
        $fixture['inventories'][0]->update(['quantity_reserved' => 0]);
        $fixture['order']->update(['order_status' => OrderStatus::Cancelled, 'payment_status' => PaymentStatus::Failed]);
        Payment::query()->create([
            'order_id' => $fixture['order']->id, 'provider' => 'mollie', 'provider_payment_id' => 'tr_open_cancelled',
            'currency' => 'EUR', 'amount' => $fixture['order']->total_amount, 'status' => PaymentAttemptStatus::Open,
            'metadata' => ['checkout_url' => 'https://payments.example.test/tr_open_cancelled', 'creation_key' => 'stable'],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('cancelled order');
        app(PaymentService::class)->createAttempt($fixture['order']->fresh(), 'https://store.test/return', 'https://store.test/webhook');
    }

    public function test_recovery_deadline_and_credential_rotation_require_review_without_another_create(): void
    {
        foreach (['deadline', 'credential'] as $case) {
            $fixture = $this->checkoutOrder();
            $this->provider->id = 'tr_'.$case;
            $this->provider->paymentCreateException = new ProviderOperationException('Lost response.', true);
            try {
                app(PaymentService::class)->createAttempt($fixture['order'], 'https://store.test/return', 'https://store.test/webhook');
            } catch (ProviderOperationException) {
            }
            $payment = $fixture['order']->payments()->firstOrFail();
            $metadata = $payment->metadata;
            if ($case === 'deadline') {
                $metadata['first_provider_attempt_at'] = now()->subMinutes(46)->toIso8601String();
            } else {
                $this->provider->credential = 'rotated-credential';
            }
            $payment->update(['metadata' => $metadata]);
            $calls = $this->provider->createCalls;

            try {
                app(PaymentService::class)->createAttempt($fixture['order']->fresh(), 'https://store.test/return', 'https://store.test/webhook');
                $this->fail('Unsafe recovery was attempted.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('manual provider review', $exception->getMessage());
            }
            $this->assertSame($calls, $this->provider->createCalls);
            $this->assertSame(PaymentAttemptStatus::ReviewRequired, $payment->fresh()->status);
            $this->assertSame(1, $fixture['inventories'][0]->fresh()->quantity_reserved);
            $this->provider->paymentCreateException = null;
            $this->provider->credential = 'test-credential-a';
        }
    }

    public function test_concurrent_second_start_does_not_duplicate_provider_creation(): void
    {
        $fixture = $this->checkoutOrder();
        $nested = null;
        $this->provider->beforePaymentCreate = function () use ($fixture, &$nested): void {
            $this->provider->beforePaymentCreate = null;
            $nested = app(PaymentService::class)->createAttempt($fixture['order']->fresh(), 'https://store.test/return', 'https://store.test/webhook');
        };

        $outer = app(PaymentService::class)->createAttempt($fixture['order'], 'https://store.test/return', 'https://store.test/webhook');
        $this->assertSame(1, $this->provider->createCalls);
        $this->assertSame($outer->id, $nested->id);
        $this->assertNotNull($outer->provider_payment_id);
    }

    public function test_stale_failure_cannot_overwrite_concurrent_success(): void
    {
        $fixture = $this->checkoutOrder();
        $this->provider->beforePaymentCreate = function () use ($fixture): void {
            $this->provider->beforePaymentCreate = null;
            $this->provider->paymentCreateException = null;
            $this->travel(3)->minutes();
            app(PaymentService::class)->createAttempt($fixture['order']->fresh(), 'https://store.test/return', 'https://store.test/webhook');
            $this->provider->paymentCreateException = new ProviderOperationException('Stale timeout.', true);
        };

        try {
            app(PaymentService::class)->createAttempt($fixture['order'], 'https://store.test/return', 'https://store.test/webhook');
            $this->fail('Outer stale failure was not propagated.');
        } catch (ProviderOperationException) {
        }
        $payment = $fixture['order']->payments()->firstOrFail();
        $this->assertNotNull($payment->provider_payment_id);
        $this->assertSame(PaymentAttemptStatus::Open, $payment->status);
        $this->assertNotNull($payment->metadata['checkout_url']);
        $this->assertArrayHasKey('ignored_stale_creation_error', $payment->metadata);
    }

    public function test_discount_limit_reductions_cannot_undercut_active_claims(): void
    {
        $discount = Discount::query()->create(['code' => 'CAP', 'name' => 'Cap', 'type' => 'fixed', 'value' => 100, 'usage_limit' => 3, 'per_customer_limit' => 2, 'active' => true]);
        foreach (['same@example.com', 'same@example.com', 'other@example.com'] as $email) {
            $fixture = $this->checkoutOrder();
            $fixture['order']->update(['discount_code' => 'CAP', 'customer_email' => $email]);
            DB::table('discount_usages')->insert(['discount_id' => $discount->id, 'order_id' => $fixture['order']->id, 'customer_email' => $email, 'amount' => 100, 'created_at' => now()]);
        }

        foreach ([[2, 2, 'usage_limit'], [3, 1, 'per_customer_limit']] as [$global, $customer, $field]) {
            try {
                app(DiscountService::class)->assertAdministrativeLimits($discount, $global, $customer);
                $this->fail('An invalid cap reduction was accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey($field, $exception->errors());
            }
        }
        app(DiscountService::class)->assertAdministrativeLimits($discount, null, null);
    }

    private function assertCancellationRejected(array $fixture): void
    {
        try {
            app(OrderCancellationService::class)->cancel($fixture['order']);
            $this->fail('Unresolved provider payment was cancelled.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('unresolved provider payment', $exception->getMessage());
        }
        $this->assertSame(OrderStatus::PendingPayment, $fixture['order']->fresh()->order_status);
        $this->assertSame(1, $fixture['inventories'][0]->fresh()->quantity_reserved);
        $this->assertSame('reserved', $fixture['order']->items()->firstOrFail()->reservation_status);
    }
}
