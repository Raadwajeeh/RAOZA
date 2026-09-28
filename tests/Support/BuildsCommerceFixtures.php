<?php

namespace Tests\Support;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Enums\VariantStatus;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Commerce\Enums\CartStatus;
use App\Domain\Commerce\Models\Cart;
use App\Domain\Commerce\Models\Order;
use App\Domain\Commerce\Services\CheckoutService;
use App\Domain\Fulfillment\Models\ShippingMethod;
use App\Domain\Inventory\Models\Inventory;
use Illuminate\Support\Str;

trait BuildsCommerceFixtures
{
    /**
     * @param  list<array{price:int, quantity?:int, stock?:int}>  $lines
     * @return array{order:Order, cart:Cart, shipping:ShippingMethod, variants:list<ProductVariant>, inventories:list<Inventory>}
     */
    protected function checkoutOrder(array $lines = [['price' => 1000]], int $shippingPrice = 0, string $shippingCurrency = 'EUR', array $overrides = []): array
    {
        $fixture = $this->cartFixture($lines, $shippingPrice, $shippingCurrency, $overrides);
        $order = app(CheckoutService::class)->createOrder($fixture['cart'], $this->checkoutData($fixture['shipping']->id, $overrides));

        return ['order' => $order, ...$fixture];
    }

    /**
     * @param  list<array{price:int, quantity?:int, stock?:int}>  $lines
     * @return array{cart:Cart, shipping:ShippingMethod, variants:list<ProductVariant>, inventories:list<Inventory>}
     */
    protected function cartFixture(array $lines = [['price' => 1000]], int $shippingPrice = 0, string $shippingCurrency = 'EUR', array $overrides = []): array
    {
        $cart = Cart::query()->create([
            'token' => (string) Str::uuid(),
            'status' => CartStatus::Active,
            'currency' => 'EUR',
            'expires_at' => now()->addDay(),
        ]);
        $variants = [];
        $inventories = [];

        foreach ($lines as $index => $line) {
            $product = Product::query()->create([
                'name' => 'Test product '.($index + 1),
                'slug' => 'test-product-'.Str::lower(Str::random(8)),
                'status' => $overrides['product_status'] ?? ProductStatus::Active,
                'base_price' => $line['price'],
                'published_at' => now()->subMinute(),
            ]);
            $variant = ProductVariant::query()->create([
                'product_id' => $product->id,
                'sku' => 'TEST-'.Str::upper(Str::random(10)),
                'status' => $overrides['variant_status'] ?? VariantStatus::Active,
                'option_signature' => 'default-'.$index,
            ]);
            $inventory = Inventory::query()->create([
                'variant_id' => $variant->id,
                'quantity_on_hand' => $line['stock'] ?? 10,
                'quantity_reserved' => 0,
            ]);
            $cart->items()->create(['variant_id' => $variant->id, 'quantity' => $line['quantity'] ?? 1]);
            $variants[] = $variant;
            $inventories[] = $inventory;
        }

        $shipping = ShippingMethod::query()->create([
            'name' => 'Test delivery',
            'code' => 'test-'.Str::lower(Str::random(8)),
            'price' => $shippingPrice,
            'currency' => $shippingCurrency,
            'active' => $overrides['shipping_active'] ?? true,
        ]);

        return compact('cart', 'shipping', 'variants', 'inventories');
    }

    protected function checkoutData(int $shippingMethodId, array $overrides = []): array
    {
        return array_merge([
            'email' => 'buyer@example.com',
            'phone' => null,
            'shipping_method_id' => $shippingMethodId,
            'discount_code' => null,
            'billing_same_as_shipping' => true,
            'shipping_first_name' => 'Test',
            'shipping_last_name' => 'Buyer',
            'shipping_company' => null,
            'shipping_street' => 'Teststraat',
            'shipping_house_number' => '10',
            'shipping_addition' => null,
            'shipping_postal_code' => '9401 AA',
            'shipping_city' => 'Assen',
            'shipping_country_code' => 'NL',
        ], $overrides);
    }
}
