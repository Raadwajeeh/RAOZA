<?php

namespace Tests\MariaDbConcurrency;

use App\Domain\Commerce\Models\Cart;
use App\Domain\Content\Models\SiteContent;
use App\Domain\Marketing\Models\AnalyticsEvent;
use App\Domain\Marketing\Models\Discount;
use App\Domain\Payments\Contracts\PaymentProvider;
use App\Domain\Payments\Enums\PaymentAttemptStatus;
use App\Domain\Payments\Models\Payment;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\BuildsCommerceFixtures;
use Tests\Support\FakePaymentProvider;
use Tests\TestCase;

final class MariaDbCompatibilityTest extends TestCase
{
    use BuildsCommerceFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
        $this->app->instance(PaymentProvider::class, new FakePaymentProvider);
    }

    public function test_server_identity_strict_mode_utc_and_innodb_are_enforced(): void
    {
        $identity = DB::selectOne('select version() as version, @@sql_mode as sql_mode, @@session.time_zone as timezone, @@tx_isolation as isolation_level, @@default_storage_engine as engine');

        $this->assertStringContainsString('MariaDB', $identity->version);
        $this->assertStringContainsString('STRICT', $identity->sql_mode);
        $this->assertSame('+00:00', $identity->timezone);
        $this->assertSame('InnoDB', $identity->engine);
        $this->assertSame('READ-COMMITTED', strtoupper($identity->isolation_level));

        $nonInnoDb = (int) DB::table('information_schema.tables')
            ->where('table_schema', DB::raw('database()'))
            ->where('table_type', 'BASE TABLE')
            ->where('engine', '!=', 'InnoDB')
            ->count();
        $this->assertSame(0, $nonInnoDb);
    }

    public function test_check_constraints_reject_invalid_inventory_money_and_refunds(): void
    {
        $fixture = $this->checkoutOrder();
        $payment = Payment::query()->create([
            'order_id' => $fixture['order']->id,
            'provider' => 'mollie',
            'provider_payment_id' => 'tr_constraint',
            'currency' => 'EUR',
            'amount' => $fixture['order']->total_amount,
            'status' => PaymentAttemptStatus::Paid,
        ]);

        $this->assertQueryRejected(fn () => DB::table('inventories')->where('id', $fixture['inventories'][0]->id)->update(['quantity_reserved' => 999]));
        $this->assertQueryRejected(fn () => DB::table('orders')->where('id', $fixture['order']->id)->update(['total_amount' => 1]));
        $this->assertQueryRejected(fn () => DB::table('refunds')->insert([
            'payment_id' => $payment->id,
            'order_id' => $fixture['order']->id,
            'amount' => 0,
            'currency' => 'EUR',
            'status' => 'requested',
            'idempotency_key' => (string) Str::uuid(),
            'requested_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    public function test_generated_columns_enforce_case_insensitive_discount_and_purchase_uniqueness(): void
    {
        $discount = Discount::query()->create(['code' => 'SAVE', 'name' => 'Save', 'type' => 'fixed', 'value' => 100, 'active' => true]);
        $normalized = DB::table('discounts')->where('id', $discount->id)->value('code_normalized');
        $this->assertSame('SAVE', $normalized);
        $this->assertQueryRejected(fn () => Discount::query()->create(['code' => 'save', 'name' => 'Duplicate', 'type' => 'fixed', 'value' => 100, 'active' => true]));

        $fixture = $this->checkoutOrder();
        AnalyticsEvent::query()->create(['event_id' => (string) Str::uuid(), 'event_name' => 'purchase', 'order_id' => $fixture['order']->id, 'occurred_at' => now()]);
        $this->assertQueryRejected(fn () => AnalyticsEvent::query()->create(['event_id' => (string) Str::uuid(), 'event_name' => 'purchase', 'order_id' => $fixture['order']->id, 'occurred_at' => now()]));
        AnalyticsEvent::query()->create(['event_id' => (string) Str::uuid(), 'event_name' => 'page_view', 'order_id' => null, 'occurred_at' => now()]);
        AnalyticsEvent::query()->create(['event_id' => (string) Str::uuid(), 'event_name' => 'page_view', 'order_id' => null, 'occurred_at' => now()]);
        $this->assertSame(3, AnalyticsEvent::query()->count());
    }

    public function test_queue_tables_json_and_dst_sensitive_timestamps_round_trip(): void
    {
        foreach (['jobs', 'job_batches', 'failed_jobs'] as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }

        $content = SiteContent::query()->create(['key' => 'mariadb-json', 'value' => ['nested' => ['enabled' => true], 'number' => 42]]);
        $this->assertSame(['nested' => ['enabled' => true], 'number' => 42], $content->fresh()->value);

        $instant = Carbon::parse('2026-10-25 02:30:00', 'Europe/Amsterdam')->utc();
        $cart = Cart::query()->create([
            'token' => (string) Str::uuid(),
            'status' => 'active',
            'currency' => 'EUR',
            'expires_at' => $instant,
        ]);
        $storedEpoch = (int) DB::table('carts')->where('id', $cart->id)->selectRaw('UNIX_TIMESTAMP(expires_at) AS epoch')->value('epoch');
        $this->assertSame($instant->timestamp, $storedEpoch);
    }

    public function test_migrations_apply_rollback_and_reapply_on_disposable_database(): void
    {
        $this->artisan('migrate:reset', ['--force' => true])->assertSuccessful();
        $this->assertFalse(Schema::hasTable('orders'));
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->assertTrue(Schema::hasColumns('discounts', ['code_normalized']));
        $this->assertTrue(Schema::hasColumns('analytics_events', ['order_id', 'event_name']));
    }

    private function assertQueryRejected(callable $operation): void
    {
        try {
            $operation();
            $this->fail('MariaDB accepted data that must be rejected by an integrity constraint.');
        } catch (QueryException $exception) {
            $this->assertNotSame('', $exception->getMessage());
        }
    }
}
