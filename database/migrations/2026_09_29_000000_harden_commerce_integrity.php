<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->unsignedBigInteger('shipping_tax_amount')->default(0)->after('shipping_amount');
        });

        $driver = DB::getDriverName();

        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement('CREATE UNIQUE INDEX discounts_code_upper_unique ON discounts (UPPER(code))');
            DB::statement("CREATE UNIQUE INDEX analytics_order_purchase_unique ON analytics_events (order_id) WHERE event_name = 'purchase' AND order_id IS NOT NULL");
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE inventories ADD CONSTRAINT inventories_quantities_consistent CHECK (quantity_on_hand >= 0 AND quantity_reserved >= 0 AND quantity_reserved <= quantity_on_hand)');
            DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_money_consistent CHECK (discount_amount <= subtotal_amount AND total_amount = subtotal_amount - discount_amount + shipping_amount AND tax_amount <= total_amount AND shipping_tax_amount <= shipping_amount AND shipping_tax_amount <= tax_amount)');
            DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_money_consistent CHECK (subtotal_amount = unit_price * quantity AND discount_amount <= subtotal_amount AND total_amount = subtotal_amount - discount_amount AND tax_amount <= total_amount)');
            DB::statement('ALTER TABLE refunds ADD CONSTRAINT refunds_amount_positive CHECK (amount > 0)');
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE refunds DROP CONSTRAINT IF EXISTS refunds_amount_positive');
            DB::statement('ALTER TABLE order_items DROP CONSTRAINT IF EXISTS order_items_money_consistent');
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_money_consistent');
            DB::statement('ALTER TABLE inventories DROP CONSTRAINT IF EXISTS inventories_quantities_consistent');
        }

        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement('DROP INDEX IF EXISTS analytics_order_purchase_unique');
            DB::statement('DROP INDEX IF EXISTS discounts_code_upper_unique');
        }

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('shipping_tax_amount');
        });
    }
};
