<?php

namespace Tests\Feature\Commerce;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Enums\VariantStatus;
use App\Domain\Commerce\Services\CheckoutService;
use App\Domain\Marketing\Services\DiscountService;
use App\Domain\Marketing\Models\Discount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\BuildsCommerceFixtures;
use Tests\TestCase;

class CheckoutIntegrityTest extends TestCase
{
    use BuildsCommerceFixtures;
    use RefreshDatabase;

    public function test_discount_shipping_and_vat_allocations_reconcile_exactly(): void
    {
        Discount::query()->create(['code' => 'ODD10', 'name' => 'Ten percent', 'type' => 'percentage', 'value' => 10, 'active' => true]);
        $fixture = $this->cartFixture([
            ['price' => 100],
            ['price' => 100],
            ['price' => 100],
        ], 605);

        $order = app(CheckoutService::class)->createOrder($fixture['cart'], $this->checkoutData($fixture['shipping']->id, ['discount_code' => 'odd10']));
        $items = $order->items()->orderBy('id')->get();

        $this->assertSame(300, $order->subtotal_amount);
        $this->assertSame(30, $order->discount_amount);
        $this->assertSame(875, $order->total_amount);
        $this->assertSame(105, $order->shipping_tax_amount);
        $this->assertSame(152, $order->tax_amount);
        $this->assertSame(30, $items->sum('discount_amount'));
        $this->assertSame(47, $items->sum('tax_amount'));
        $this->assertSame([16, 16, 15], $items->pluck('tax_amount')->all());
    }

    public function test_checkout_uses_current_server_price_and_snapshots_it(): void
    {
        $fixture = $this->cartFixture([['price' => 1000]]);
        $fixture['variants'][0]->update(['price_override' => 2345]);

        $data = $this->checkoutData($fixture['shipping']->id, ['subtotal_amount' => 1, 'total_amount' => 1, 'tax_amount' => 1]);
        $order = app(CheckoutService::class)->createOrder($fixture['cart'], $data);
        $fixture['variants'][0]->update(['price_override' => 9999]);

        $this->assertSame(2345, $order->total_amount);
        $this->assertSame(2345, $order->items()->firstOrFail()->unit_price);
        $this->assertSame(2345, $order->fresh()->items()->firstOrFail()->unit_price);
    }

    public function test_inactive_or_wrong_currency_shipping_cannot_be_used(): void
    {
        $inactive = $this->cartFixture([['price' => 1000]], 495, 'EUR', ['shipping_active' => false]);

        try {
            app(CheckoutService::class)->createOrder($inactive['cart'], $this->checkoutData($inactive['shipping']->id));
            $this->fail('Inactive shipping method was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('shipping_method_id', $exception->errors());
        }

        $wrongCurrency = $this->cartFixture([['price' => 1000]], 495, 'USD');
        $this->expectException(ValidationException::class);
        app(CheckoutService::class)->createOrder($wrongCurrency['cart'], $this->checkoutData($wrongCurrency['shipping']->id));
    }

    public function test_disabled_catalog_records_and_insufficient_inventory_fail_checkout(): void
    {
        foreach ([
            ['product_status' => ProductStatus::Draft],
            ['variant_status' => VariantStatus::Inactive],
        ] as $override) {
            $fixture = $this->cartFixture([['price' => 1000]], 0, 'EUR', $override);
            try {
                app(CheckoutService::class)->createOrder($fixture['cart'], $this->checkoutData($fixture['shipping']->id));
                $this->fail('Unavailable catalog record was accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('cart', $exception->errors());
            }
        }

        $insufficient = $this->cartFixture([['price' => 1000, 'quantity' => 2, 'stock' => 1]]);
        $this->expectException(ValidationException::class);
        app(CheckoutService::class)->createOrder($insufficient['cart'], $this->checkoutData($insufficient['shipping']->id));
    }

    public function test_same_cart_is_converted_to_one_order_and_reserved_once(): void
    {
        $fixture = $this->cartFixture([['price' => 1500, 'quantity' => 2, 'stock' => 3]]);
        $service = app(CheckoutService::class);
        $data = $this->checkoutData($fixture['shipping']->id);

        $first = $service->createOrder($fixture['cart'], $data);
        $second = $service->createOrder($fixture['cart']->fresh(), $data);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(2, $fixture['inventories'][0]->fresh()->quantity_reserved);
    }

    public function test_fixed_discount_cannot_reduce_merchandise_below_zero(): void
    {
        Discount::query()->create(['code' => 'BOUNDARY', 'name' => 'Boundary', 'type' => 'fixed', 'value' => 5000, 'active' => true]);
        $fixture = $this->cartFixture([['price' => 999]], 100);
        $order = app(CheckoutService::class)->createOrder($fixture['cart'], $this->checkoutData($fixture['shipping']->id, ['discount_code' => 'boundary']));

        $this->assertSame(999, $order->discount_amount);
        $this->assertSame(100, $order->total_amount);
        $this->assertSame(0, $order->items()->firstOrFail()->total_amount);
    }

    public function test_discount_usage_limits_follow_the_existing_paid_order_definition(): void
    {
        $discount = Discount::query()->create(['code' => 'ONCE', 'name' => 'Once', 'type' => 'fixed', 'value' => 100, 'usage_limit' => 1, 'active' => true]);
        $fixture = $this->checkoutOrder();
        $fixture['order']->update(['discount_code' => 'ONCE']);
        $fixture['order']->refresh();
        DB::table('discount_usages')->insert(['discount_id' => $discount->id, 'order_id' => $fixture['order']->id, 'customer_email' => 'first@example.com', 'amount' => 100, 'created_at' => now()]);

        $quote = app(DiscountService::class)->quote('once', 1000, 'second@example.com');
        $this->assertSame(100, $quote['amount']);

        $fixture['order']->update(['payment_status' => 'paid']);
        $this->expectException(ValidationException::class);
        app(DiscountService::class)->quote('ONCE', 1000, 'second@example.com');
    }
}
