<?php

use App\Domain\Commerce\Models\Cart;
use App\Domain\Commerce\Models\Order;
use App\Domain\Commerce\Services\CheckoutService;
use App\Domain\Commerce\Services\OrderCancellationService;
use App\Domain\Commerce\Services\PendingOrderExpirationService;
use App\Domain\Inventory\Models\Inventory;
use App\Domain\Invoicing\Enums\InvoiceType;
use App\Domain\Invoicing\Services\InvoiceNumberService;
use App\Domain\Marketing\Models\Discount;
use App\Domain\Marketing\Services\DiscountService;
use App\Domain\Payments\Contracts\PaymentProvider;
use App\Domain\Payments\Exceptions\ProviderOperationException;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Services\PaymentService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Tests\Support\FakePaymentProvider;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function emitMariaDb(array $message): void
{
    fwrite(STDOUT, json_encode($message, JSON_THROW_ON_ERROR).PHP_EOL);
    fflush(STDOUT);
}

function mariaDbBarrier(string $name): void
{
    emitMariaDb(['type' => 'barrier', 'name' => $name, 'connection_id' => DB::selectOne('select connection_id() as id')->id]);
    if (fgets(STDIN) === false) {
        throw new RuntimeException('Barrier input closed.');
    }
}

try {
    $connection = config('database.connections.mysql');
    if (getenv('RAOZA_MARIADB_TEST_OPT_IN') !== 'phase7d2-disposable'
        || config('database.default') !== 'mysql'
        || ($connection['host'] ?? null) !== '127.0.0.1'
        || ! str_starts_with((string) ($connection['database'] ?? ''), 'raoza_phase7d2_')
        || ! str_starts_with((string) ($connection['username'] ?? ''), 'raoza_phase7d2_')) {
        throw new RuntimeException('Unsafe MariaDB worker configuration refused.');
    }
    $identity = DB::selectOne("select database() as database_name, substring_index(current_user(), '@', 1) as username, @@port as port, version() as version, @@default_storage_engine as engine");
    if ($identity->database_name !== $connection['database']
        || $identity->username !== $connection['username']
        || (string) $identity->port !== (string) $connection['port']
        || ! str_contains(strtolower((string) $identity->version), 'mariadb')
        || strcasecmp((string) $identity->engine, 'InnoDB') !== 0) {
        throw new RuntimeException('MariaDB worker identity validation failed.');
    }
    $grants = collect(DB::select('show grants for current_user()'))->flatMap(fn (object $row): array => array_values((array) $row))->implode("\n");
    if (preg_match('/GRANT ALL PRIVILEGES ON \*\.\*|WITH GRANT OPTION|SUPER|FILE/i', $grants)) {
        throw new RuntimeException('Unsafe MariaDB worker privileges refused.');
    }
    DB::statement('set session innodb_lock_wait_timeout = 8');

    $action = $argv[1] ?? '';
    $payload = json_decode(base64_decode($argv[2] ?? '', true) ?: '[]', true, flags: JSON_THROW_ON_ERROR);
    $provider = new FakePaymentProvider;
    $provider->id = $payload['provider_id'] ?? 'tr_mariadb_worker';
    $provider->status = $payload['provider_status'] ?? 'open';
    app()->instance(PaymentProvider::class, $provider);

    $result = match ($action) {
        'create_pause' => (function () use ($payload, $provider): array {
            $provider->beforePaymentCreate = fn () => mariaDbBarrier('provider_create');
            if (isset($payload['failure_uncertain'])) {
                $provider->paymentCreateException = new ProviderOperationException('Injected MariaDB provider failure.', (bool) $payload['failure_uncertain']);
            }
            $payment = app(PaymentService::class)->createAttempt(Order::findOrFail($payload['order_id']), 'https://store.test/return', 'https://store.test/webhook');

            return ['payment_id' => $payment->id, 'provider_payment_id' => $payment->provider_payment_id, 'status' => $payment->status->value, 'create_calls' => $provider->createCalls];
        })(),
        'create_start' => (function () use ($payload, $provider): array {
            mariaDbBarrier('ready');
            $payment = app(PaymentService::class)->createAttempt(Order::findOrFail($payload['order_id']), 'https://store.test/return', 'https://store.test/webhook');

            return ['payment_id' => $payment->id, 'provider_payment_id' => $payment->provider_payment_id, 'status' => $payment->status->value, 'create_calls' => $provider->createCalls];
        })(),
        'sync_start' => (function () use ($payload): array {
            mariaDbBarrier('ready');
            $sync = app(PaymentService::class)->sync(Payment::findOrFail($payload['payment_id']), $payload['event_type']);

            return ['became_paid' => $sync->becamePaid, 'status' => $sync->payment->status->value];
        })(),
        'cancel_start' => (function () use ($payload): array {
            mariaDbBarrier('ready');
            $order = app(OrderCancellationService::class)->cancel(Order::findOrFail($payload['order_id']), null, 'mariadb_race');

            return ['order_status' => $order->order_status->value];
        })(),
        'checkout_start' => (function () use ($payload): array {
            mariaDbBarrier('ready');
            $order = app(CheckoutService::class)->createOrder(Cart::findOrFail($payload['cart_id']), $payload['data']);

            return ['order_id' => $order->id];
        })(),
        'expire_start' => (function (): array {
            mariaDbBarrier('ready');

            return app(PendingOrderExpirationService::class)->expire();
        })(),
        'stale_failure_start' => (function () use ($payload): array {
            $payment = Payment::findOrFail($payload['payment_id']);
            mariaDbBarrier('ready');
            $method = new ReflectionMethod(PaymentService::class, 'persistCreationFailure');
            $method->invoke(app(PaymentService::class), $payment, new ProviderOperationException('Delayed stale timeout.', true), true);
            $payment = $payment->fresh();

            return ['provider_payment_id' => $payment->provider_payment_id, 'status' => $payment->status->value, 'metadata' => $payment->metadata];
        })(),
        'discount_claim_hold' => DB::transaction(function () use ($payload): array {
            $discount = Discount::query()->whereKey($payload['discount_id'])->lockForUpdate()->firstOrFail();
            mariaDbBarrier('discount_locked');
            DB::table('discount_usages')->insert(['discount_id' => $discount->id, 'order_id' => $payload['order_id'], 'customer_email' => $payload['email'], 'amount' => 100, 'created_at' => now()]);

            return ['claimed' => true];
        }, 1),
        'discount_admin_start' => (function () use ($payload): array {
            mariaDbBarrier('ready');

            return DB::transaction(function () use ($payload): array {
                $discount = Discount::query()->whereKey($payload['discount_id'])->lockForUpdate()->firstOrFail();
                app(DiscountService::class)->assertAdministrativeLimits($discount, $payload['usage_limit'], null);
                $discount->update(['usage_limit' => $payload['usage_limit']]);

                return ['usage_limit' => $discount->fresh()->usage_limit];
            }, 1);
        })(),
        'hold_inventory_lock' => DB::transaction(function () use ($payload): array {
            Inventory::query()->whereKey($payload['inventory_id'])->lockForUpdate()->firstOrFail();
            mariaDbBarrier('inventory_locked');

            return ['held' => true];
        }, 1),
        'deadlock_pair' => (function () use ($payload): array {
            $attempts = 0;
            DB::transaction(function () use ($payload, &$attempts): void {
                $attempts++;
                Inventory::query()->whereKey($payload['first_id'])->lockForUpdate()->firstOrFail();
                if ($attempts === 1) {
                    mariaDbBarrier('first_locked');
                }
                Inventory::query()->whereKey($payload['second_id'])->lockForUpdate()->firstOrFail();
            }, 3);

            return ['attempts' => $attempts];
        })(),
        'invoice_number_start' => (function () use ($payload): array {
            config()->set('invoicing.numbering.scope', $payload['scope']);
            config()->set('invoicing.numbering.invoice_prefix', $payload['prefix']);
            config()->set('invoicing.numbering.padding', $payload['padding']);
            mariaDbBarrier('ready');
            return DB::transaction(fn () => app(InvoiceNumberService::class)->next(
                InvoiceType::Invoice,
                \Illuminate\Support\Carbon::parse($payload['issued_at']),
            ), 5);
        })(),
        default => throw new RuntimeException("Unknown worker action: {$action}"),
    };

    emitMariaDb(['type' => 'result', 'ok' => true, 'result' => $result]);
} catch (Throwable $exception) {
    emitMariaDb(['type' => 'result', 'ok' => false, 'exception' => $exception::class, 'message' => $exception->getMessage()]);
}
