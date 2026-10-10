<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->string('reservation_status', 16)->default('reserved')->index();
        });

        DB::table('order_items')
            ->whereIn('order_id', DB::table('orders')->select('id')->whereIn('payment_status', ['paid', 'partially_refunded', 'refunded']))
            ->update(['reservation_status' => 'committed']);

        DB::table('order_items')
            ->whereIn('order_id', DB::table('orders')->select('id')->where('order_status', 'cancelled')->whereNotIn('payment_status', ['paid', 'partially_refunded', 'refunded']))
            ->update(['reservation_status' => 'released']);
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropIndex(['reservation_status']);
            $table->dropColumn('reservation_status');
        });
    }
};
