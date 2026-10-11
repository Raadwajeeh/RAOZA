<?php

use App\Domain\Commerce\Models\Cart;
use App\Domain\Commerce\Models\Order;
use App\Domain\Commerce\Services\CheckoutService;
use App\Domain\Commerce\Services\OrderCancellationService;
use App\Domain\Commerce\Services\PendingOrderExpirationService;
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

function emit(array $message): void
{
    fwrite(STDOUT, json_encode($message, JSON_THROW_ON_ERROR).PHP_EOL);
    fflush(STDOUT);
}

function barrier(string $name): void
{
    emit(['type' => 'barrier', 'name' => $name, 'pid' => DB::selectOne('select pg_backend_pid() as pid')->pid]);
    if (fgets(STDIN) === false) {
        throw new RuntimeException('Barrier input closed.');
    }
}

try {
    if (getenv('RAOZA_POSTGRES_TEST_OPT_IN') !== 'phase7b3-disposable'
        || config('database.default') !== 'pgsql'
        || config('database.connections.pgsql.host') !== '127.0.0.1'
        || ! str_starts_with((string) config('database.connections.pgsql.database'), 'raoza_phase7b3_')
        || ! str_starts_with((string) config('database.connections.pgsql.username'), 'raoza_phase7b3_')) {
        throw new RuntimeException('Unsafe PostgreSQL worker configuration refused.');
    }
    $identity = DB::selectOne('select current_database() as database, current_user as username, r.rolsuper from pg_roles r where r.rolname=current_user');
    if ($identity->database !== config('database.connections.pgsql.database')
        || $identity->username !== config('database.connections.pgsql.username')
        || $identity->rolsuper) {
        throw new RuntimeException('PostgreSQL worker identity validation failed.');
    }
    DB::statement("set statement_timeout = '10s'");
    DB::statement("set lock_timeout = '8s'");

    $action = $argv[1] ?? '';
    $payload = json_decode(base64_decode($argv[2] ?? '', true) ?: '[]', true, flags: JSON_THROW_ON_ERROR);
    $provider = new FakePaymentProvider;
    $provider->id = $payload['provider_id'] ?? 'tr_pg_worker';
    $provider->status = $payload['provider_status'] ?? 'open';
    app()->instance(PaymentProvider::class, $provider);

    $result = match ($action) {
        'create_pause' => (function () use ($payload, $provider): array {
            $provider->beforePaymentCreate = fn () => barrier('provider_create');
            if (isset($payload['failure_uncertain'])) {
                $provider->paymentCreateException = new ProviderOperationException('Injected PostgreSQL provider failure.', (bool) $payload['failure_uncertain']);
            }
            $payment = app(PaymentService::class)->createAttempt(Order::findOrFail($payload['order_id']), 'https://store.test/return', 'https://store.test/webhook');

            return ['payment_id' => $payment->id, 'provider_payment_id' => $payment->provider_payment_id, 'status' => $payment->status->value, 'create_calls' => $provider->createCalls];
        })(),
        'create_start' => (function () use ($payload, $provider): array {
            barrier('ready');
            $payment = app(PaymentService::class)->createAttempt(Order::findOrFail($payload['order_id']), 'https://store.test/return', 'https://store.test/webhook');

            return ['payment_id' => $payment->id, 'provider_payment_id' => $payment->provider_payment_id, 'status' => $payment->status->value, 'create_calls' => $provider->createCalls];
        })(),
        'sync_start' => (function () use ($payload): array {
            barrier('ready');
            $sync = app(PaymentService::class)->sync(Payment::findOrFail($payload['payment_id']), $payload['event_type']);

            return ['became_paid' => $sync->becamePaid, 'status' => $sync->payment->status->value];
        })(),
        'cancel_start' => (function () use ($payload): array {
            barrier('ready');
            $order = app(OrderCancellationService::class)->cancel(Order::findOrFail($payload['order_id']), null, 'postgres_race');

            return ['order_status' => $order->order_status->value];
        })(),
        'checkout_start' => (function () use ($payload): array {
            barrier('ready');
            $order = app(CheckoutService::class)->createOrder(Cart::findOrFail($payload['cart_id']), $payload['data']);

            return ['order_id' => $order->id];
        })(),
        'expire_start' => (function (): array {
            barrier('ready');

            return app(PendingOrderExpirationService::class)->expire();
        })(),
        'stale_failure_start' => (function () use ($payload): array {
            $payment = Payment::findOrFail($payload['payment_id']);
            barrier('ready');
            $method = new ReflectionMethod(PaymentService::class, 'persistCreationFailure');
            $method->invoke(app(PaymentService::class), $payment, new ProviderOperationException('Delayed stale timeout.', true), true);
            $payment = $payment->fresh();

            return ['provider_payment_id' => $payment->provider_payment_id, 'status' => $payment->status->value, 'metadata' => $payment->metadata];
        })(),
        'discount_claim_hold' => DB::transaction(function () use ($payload): array {
            $discount = Discount::query()->whereKey($payload['discount_id'])->lockForUpdate()->firstOrFail();
            barrier('discount_locked');
            DB::table('discount_usages')->insert([
                'discount_id' => $discount->id,
                'order_id' => $payload['order_id'],
                'customer_email' => $payload['email'],
                'amount' => 100,
                'created_at' => now(),
            ]);

            return ['claimed' => true];
        }, 1),
        'discount_admin_start' => (function () use ($payload): array {
            barrier('ready');

            return DB::transaction(function () use ($payload): array {
                $discount = Discount::query()->whereKey($payload['discount_id'])->lockForUpdate()->firstOrFail();
                app(DiscountService::class)->assertAdministrativeLimits($discount, $payload['usage_limit'], null);
                $discount->update(['usage_limit' => $payload['usage_limit']]);

                return ['usage_limit' => $discount->fresh()->usage_limit];
            }, 1);
        })(),
        default => throw new RuntimeException("Unknown worker action: {$action}"),
    };

    emit(['type' => 'result', 'ok' => true, 'result' => $result]);
} catch (Throwable $exception) {
    emit(['type' => 'result', 'ok' => false, 'exception' => $exception::class, 'message' => $exception->getMessage()]);
}
