<?php

namespace Tests\MariaDbConcurrency;

use App\Domain\Commerce\Enums\CartStatus;
use App\Domain\Commerce\Enums\OrderStatus;
use App\Domain\Commerce\Enums\PaymentStatus;
use App\Domain\Commerce\Models\Cart;
use App\Domain\Commerce\Services\CheckoutService;
use App\Domain\Commerce\Services\PendingOrderExpirationService;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\Marketing\Models\Discount;
use App\Domain\Payments\Contracts\PaymentProvider;
use App\Domain\Payments\Enums\PaymentAttemptStatus;
use App\Domain\Payments\Services\PaymentService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\BuildsCommerceFixtures;
use Tests\Support\FakePaymentProvider;
use Tests\TestCase;

final class CommerceMariaDbConcurrencyTest extends TestCase
{
    use BuildsCommerceFixtures;

    private FakePaymentProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
        DB::statement('set session innodb_lock_wait_timeout = 8');
        $this->provider = new FakePaymentProvider;
        $this->app->instance(PaymentProvider::class, $this->provider);
    }

    public function test_payment_creation_serializes_with_expiry_and_commits_owned_stock_once(): void
    {
        $fixture = $this->checkoutOrder([['price' => 1000, 'stock' => 2]]);
        $fixture['order']->update(['placed_at' => now()->subHour()]);
        $worker = $this->startWorker('create_pause', ['order_id' => $fixture['order']->id, 'provider_id' => 'tr_maria_creation']);
        $this->waitFor($worker, 'provider_create');
        $this->assertSame(0, app(PendingOrderExpirationService::class)->expire()['expired']);
        $this->assertTrue($this->releaseAndFinish($worker)['ok']);

        $this->provider->status = 'paid';
        $this->provider->id = 'tr_maria_creation';
        $payment = $fixture['order']->payments()->firstOrFail();
        app(PaymentService::class)->sync($payment, 'maria_paid');
        app(PaymentService::class)->sync($payment->fresh(), 'maria_paid_duplicate');

        $this->assertSame(1, $fixture['inventories'][0]->fresh()->quantity_on_hand);
        $this->assertSame(0, $fixture['inventories'][0]->fresh()->quantity_reserved);
        $this->assertSame(1, InventoryMovement::query()->count());
    }

    public function test_uncertain_creation_failure_stays_protected_until_authoritative_failure(): void
    {
        $fixture = $this->checkoutOrder();
        $fixture['order']->update(['placed_at' => now()->subHour()]);
        $worker = $this->startWorker('create_pause', ['order_id' => $fixture['order']->id, 'provider_id' => 'tr_maria_failure', 'failure_uncertain' => true]);
        $this->waitFor($worker, 'provider_create');
        $this->assertSame(0, app(PendingOrderExpirationService::class)->expire()['expired']);
        $this->assertFalse($this->releaseAndFinish($worker)['ok']);
        $this->assertSame(PaymentAttemptStatus::Created, $fixture['order']->payments()->firstOrFail()->status);
        $this->assertSame(0, app(PendingOrderExpirationService::class)->expire()['expired']);
    }

    public function test_admin_cancellation_and_paid_webhook_serialize_safely(): void
    {
        $fixture = $this->checkoutOrder();
        $this->provider->id = 'tr_maria_cancel_sync';
        $payment = app(PaymentService::class)->createAttempt($fixture['order'], 'https://store.test/return', 'https://store.test/webhook');
        $cancel = $this->startWorker('cancel_start', ['order_id' => $fixture['order']->id]);
        $sync = $this->startWorker('sync_start', ['payment_id' => $payment->id, 'provider_id' => 'tr_maria_cancel_sync', 'provider_status' => 'paid', 'event_type' => 'same_paid_event']);
        $this->waitFor($cancel, 'ready');
        $this->waitFor($sync, 'ready');
        $this->release($cancel);
        $this->release($sync);
        $results = [$this->finish($cancel), $this->finish($sync)];

        $this->assertSame(1, collect($results)->where('ok', true)->count());
        $this->assertSame(PaymentStatus::Paid, $fixture['order']->fresh()->payment_status);
        $this->assertSame(1, InventoryMovement::query()->count());
    }

    public function test_concurrent_creation_reuses_claim_and_stale_failure_cannot_regress_success(): void
    {
        $fixture = $this->checkoutOrder();
        $first = $this->startWorker('create_pause', ['order_id' => $fixture['order']->id, 'provider_id' => 'tr_maria_winner']);
        $this->waitFor($first, 'provider_create');
        $second = $this->startWorker('create_start', ['order_id' => $fixture['order']->id, 'provider_id' => 'tr_maria_loser']);
        $this->waitFor($second, 'ready');
        $secondResult = $this->releaseAndFinish($second);
        $this->assertTrue($secondResult['ok']);
        $this->assertSame(0, $secondResult['result']['create_calls']);
        $this->assertTrue($this->releaseAndFinish($first)['ok']);

        $payment = $fixture['order']->payments()->firstOrFail();
        $stale = $this->startWorker('stale_failure_start', ['payment_id' => $payment->id]);
        $this->waitFor($stale, 'ready');
        $this->assertTrue($this->releaseAndFinish($stale)['ok']);
        $this->assertSame('tr_maria_winner', $payment->fresh()->provider_payment_id);
        $this->assertArrayHasKey('ignored_stale_creation_error', $payment->fresh()->metadata);
    }

    public function test_two_processes_competing_for_final_sku_allow_exactly_one_order(): void
    {
        $fixture = $this->cartFixture([['price' => 1000, 'stock' => 1]]);
        $second = Cart::query()->create(['token' => (string) Str::uuid(), 'status' => CartStatus::Active, 'currency' => 'EUR', 'expires_at' => now()->addDay()]);
        $second->items()->create(['variant_id' => $fixture['variants'][0]->id, 'quantity' => 1]);
        $a = $this->startWorker('checkout_start', ['cart_id' => $fixture['cart']->id, 'data' => $this->checkoutData($fixture['shipping']->id, ['email' => 'a@example.test'])]);
        $b = $this->startWorker('checkout_start', ['cart_id' => $second->id, 'data' => $this->checkoutData($fixture['shipping']->id, ['email' => 'b@example.test'])]);
        $this->waitFor($a, 'ready');
        $this->waitFor($b, 'ready');
        $this->release($a);
        $this->release($b);
        $results = [$this->finish($a), $this->finish($b)];

        $this->assertSame(1, collect($results)->where('ok', true)->count());
        $this->assertSame(1, collect($results)->where('ok', false)->count());
        $this->assertSame(1, DB::table('orders')->count());
    }

    public function test_two_processes_competing_for_final_discount_use_allow_exactly_one_claim(): void
    {
        $discount = Discount::query()->create(['code' => 'FINAL', 'name' => 'Final', 'type' => 'fixed', 'value' => 100, 'usage_limit' => 1, 'active' => true]);
        $fixture = $this->cartFixture([['price' => 1000, 'stock' => 2]]);
        $second = Cart::query()->create(['token' => (string) Str::uuid(), 'status' => CartStatus::Active, 'currency' => 'EUR', 'expires_at' => now()->addDay()]);
        $second->items()->create(['variant_id' => $fixture['variants'][0]->id, 'quantity' => 1]);
        $a = $this->startWorker('checkout_start', ['cart_id' => $fixture['cart']->id, 'data' => $this->checkoutData($fixture['shipping']->id, ['email' => 'a@example.test', 'discount_code' => 'FINAL'])]);
        $b = $this->startWorker('checkout_start', ['cart_id' => $second->id, 'data' => $this->checkoutData($fixture['shipping']->id, ['email' => 'b@example.test', 'discount_code' => 'FINAL'])]);
        $this->waitFor($a, 'ready');
        $this->waitFor($b, 'ready');
        $this->release($a);
        $this->release($b);
        $results = [$this->finish($a), $this->finish($b)];

        $this->assertSame(1, collect($results)->where('ok', true)->count());
        $this->assertSame(1, DB::table('discount_usages')->where('discount_id', $discount->id)->count());
    }

    public function test_discount_claim_blocks_invalid_admin_cap_reduction(): void
    {
        $discount = Discount::query()->create(['code' => 'ADMIN', 'name' => 'Admin', 'type' => 'fixed', 'value' => 100, 'usage_limit' => 2, 'active' => true]);
        $fixture = $this->checkoutOrder();
        $claim = $this->startWorker('discount_claim_hold', ['discount_id' => $discount->id, 'order_id' => $fixture['order']->id, 'email' => 'buyer@example.com']);
        $this->waitFor($claim, 'discount_locked');
        $admin = $this->startWorker('discount_admin_start', ['discount_id' => $discount->id, 'usage_limit' => 0]);
        $this->waitFor($admin, 'ready');
        $this->release($admin);
        usleep(200_000);
        $this->assertTrue(proc_get_status($admin['process'])['running']);
        $this->assertTrue($this->releaseAndFinish($claim)['ok']);
        $this->assertFalse($this->finish($admin)['ok']);
        $this->assertSame(2, $discount->fresh()->usage_limit);
    }

    public function test_duplicate_paid_events_from_two_processes_commit_exactly_once(): void
    {
        $fixture = $this->checkoutOrder([['price' => 1000, 'stock' => 2]]);
        $this->provider->id = 'tr_maria_duplicate';
        $payment = app(PaymentService::class)->createAttempt($fixture['order'], 'https://store.test/return', 'https://store.test/webhook');
        $payload = ['payment_id' => $payment->id, 'provider_id' => 'tr_maria_duplicate', 'provider_status' => 'paid', 'event_type' => 'identical_paid'];
        $a = $this->startWorker('sync_start', $payload);
        $b = $this->startWorker('sync_start', $payload);
        $this->waitFor($a, 'ready');
        $this->waitFor($b, 'ready');
        $this->release($a);
        $this->release($b);
        $results = [$this->finish($a), $this->finish($b)];

        $this->assertSame(2, collect($results)->where('ok', true)->count());
        $this->assertSame(1, collect($results)->filter(fn ($r) => ($r['result']['became_paid'] ?? false) === true)->count());
        $this->assertSame(1, InventoryMovement::query()->count());
        $this->assertSame(1, DB::table('payment_events')->count());
    }

    public function test_payment_retry_and_expiry_serialize_without_losing_stock_or_discount_claim(): void
    {
        $discount = Discount::query()->create(['code' => 'RETRY', 'name' => 'Retry', 'type' => 'fixed', 'value' => 100, 'usage_limit' => 1, 'active' => true]);
        $fixture = $this->checkoutOrder([['price' => 1000, 'stock' => 2]], overrides: ['discount_code' => 'RETRY']);
        $fixture['order']->payments()->create(['provider' => 'mollie', 'currency' => 'EUR', 'amount' => $fixture['order']->total_amount, 'status' => PaymentAttemptStatus::Failed]);
        $fixture['order']->update(['payment_status' => PaymentStatus::Failed, 'placed_at' => now()->subHour()]);
        $retry = $this->startWorker('create_start', ['order_id' => $fixture['order']->id, 'provider_id' => 'tr_maria_retry']);
        $expiry = $this->startWorker('expire_start', []);
        $this->waitFor($retry, 'ready');
        $this->waitFor($expiry, 'ready');
        $this->release($retry);
        $this->release($expiry);
        $results = [$this->finish($retry), $this->finish($expiry)];

        $this->assertSame(2, collect($results)->where('ok', true)->count());
        $this->assertSame(OrderStatus::PendingPayment, $fixture['order']->fresh()->order_status);
        $this->assertSame('reserved', $fixture['order']->items()->firstOrFail()->reservation_status);
        $this->assertSame(1, DB::table('discount_usages')->where('discount_id', $discount->id)->count());
    }

    public function test_paid_retry_cannot_consume_another_orders_reservation(): void
    {
        $fixture = $this->checkoutOrder([['price' => 1000, 'stock' => 2]]);
        $this->provider->id = 'tr_maria_late';
        $payment = app(PaymentService::class)->createAttempt($fixture['order'], 'https://store.test/return', 'https://store.test/webhook');
        $this->provider->status = 'failed';
        app(PaymentService::class)->sync($payment, 'failed');
        $second = Cart::query()->create(['token' => (string) Str::uuid(), 'status' => CartStatus::Active, 'currency' => 'EUR', 'expires_at' => now()->addDay()]);
        $second->items()->create(['variant_id' => $fixture['variants'][0]->id, 'quantity' => 1]);
        $this->checkoutOrderFromCart($second, $fixture['shipping']->id);
        $worker = $this->startWorker('sync_start', ['payment_id' => $payment->id, 'provider_id' => 'tr_maria_late', 'provider_status' => 'paid', 'event_type' => 'late_paid']);
        $this->waitFor($worker, 'ready');
        $result = $this->releaseAndFinish($worker);

        $this->assertFalse($result['ok']);
        $this->assertSame(PaymentStatus::Failed, $fixture['order']->fresh()->payment_status);
    }

    public function test_deadlocks_are_retried_with_a_bounded_attempt_count(): void
    {
        $fixture = $this->cartFixture([['price' => 1000, 'stock' => 2], ['price' => 1200, 'stock' => 2]]);
        $ids = collect($fixture['inventories'])->pluck('id')->all();
        $a = $this->startWorker('deadlock_pair', ['first_id' => $ids[0], 'second_id' => $ids[1]]);
        $b = $this->startWorker('deadlock_pair', ['first_id' => $ids[1], 'second_id' => $ids[0]]);
        $this->waitFor($a, 'first_locked');
        $this->waitFor($b, 'first_locked');
        $this->release($a);
        $this->release($b);
        $results = [$this->finish($a), $this->finish($b)];

        $this->assertSame(2, collect($results)->where('ok', true)->count());
        $this->assertTrue(collect($results)->contains(fn ($result) => $result['result']['attempts'] > 1));
        $this->assertTrue(collect($results)->every(fn ($result) => $result['result']['attempts'] <= 3));
    }

    public function test_lock_wait_timeout_fails_without_mutating_inventory(): void
    {
        $fixture = $this->cartFixture();
        $inventory = $fixture['inventories'][0];
        $worker = $this->startWorker('hold_inventory_lock', ['inventory_id' => $inventory->id]);
        $this->waitFor($worker, 'inventory_locked');
        DB::statement('set session innodb_lock_wait_timeout = 1');
        try {
            DB::transaction(fn () => DB::table('inventories')->where('id', $inventory->id)->lockForUpdate()->first(), 1);
            $this->fail('The lock wait unexpectedly succeeded.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('1205', $exception->getMessage());
        } finally {
            $this->assertTrue($this->releaseAndFinish($worker)['ok']);
        }
        $this->assertSame(0, $inventory->fresh()->quantity_reserved);
    }

    public function test_invoice_numbers_are_unique_and_sequential_under_concurrency(): void
    {
        $payload = ['scope' => 'yearly', 'prefix' => 'INV', 'padding' => 6, 'issued_at' => '2026-10-14T12:00:00Z'];
        $a = $this->startWorker('invoice_number_start', $payload);
        $b = $this->startWorker('invoice_number_start', $payload);
        $this->waitFor($a, 'ready');
        $this->waitFor($b, 'ready');
        $this->release($a);
        $this->release($b);
        $results = [$this->finish($a), $this->finish($b)];

        $this->assertTrue(collect($results)->every(fn ($result) => $result['ok']));
        $numbers = collect($results)->pluck('result.number')->sort()->values()->all();
        $this->assertSame(['INV-2026-000001', 'INV-2026-000002'], $numbers);
        $this->assertSame(3, (int) DB::table('invoice_number_sequences')->where('document_type', 'invoice')->where('scope_key', '2026')->value('next_value'));
    }

    private function checkoutOrderFromCart(Cart $cart, int $shippingId): void
    {
        app(CheckoutService::class)->createOrder($cart, $this->checkoutData($shippingId, ['email' => 'second@example.test']));
    }

    private function startWorker(string $action, array $payload): array
    {
        $command = [PHP_BINARY, __DIR__.'/MariaDbProcessWorker.php', $action, base64_encode(json_encode($payload, JSON_THROW_ON_ERROR))];
        $pipes = [];
        $process = proc_open($command, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, base_path(), null, ['bypass_shell' => true]);
        $this->assertIsResource($process);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        return compact('process', 'pipes');
    }

    private function waitFor(array &$worker, string $barrier): array
    {
        $deadline = microtime(true) + 15;
        while (microtime(true) < $deadline) {
            $line = fgets($worker['pipes'][1]);
            if ($line !== false) {
                $message = json_decode(trim($line), true, flags: JSON_THROW_ON_ERROR);
                if (($message['type'] ?? null) === 'barrier' && ($message['name'] ?? null) === $barrier) {
                    return $message;
                }
                if (($message['type'] ?? null) === 'result') {
                    $this->fail('Worker ended before barrier: '.json_encode($message));
                }
            }
            usleep(20_000);
        }
        $this->fail('Timed out waiting for worker barrier. STDERR: '.stream_get_contents($worker['pipes'][2]));
    }

    private function release(array &$worker): void
    {
        fwrite($worker['pipes'][0], "continue\n");
        fflush($worker['pipes'][0]);
    }

    private function releaseAndFinish(array &$worker): array
    {
        $this->release($worker);

        return $this->finish($worker);
    }

    private function finish(array &$worker): array
    {
        $deadline = microtime(true) + 20;
        $result = null;
        while (microtime(true) < $deadline) {
            $line = fgets($worker['pipes'][1]);
            if ($line !== false) {
                $message = json_decode(trim($line), true, flags: JSON_THROW_ON_ERROR);
                if (($message['type'] ?? null) === 'result') {
                    $result = $message;
                    break;
                }
            }
            usleep(20_000);
        }
        $stderr = stream_get_contents($worker['pipes'][2]);
        foreach ($worker['pipes'] as $pipe) {
            fclose($pipe);
        }
        proc_terminate($worker['process']);
        proc_close($worker['process']);
        $this->assertNotNull($result, 'Worker timed out. STDERR: '.$stderr);

        return $result;
    }
}
