<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_carriers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('code', 80)->unique();
            $table->string('integration_key', 32)->default('manual');
            $table->text('tracking_url_template')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->unsignedInteger('position')->default(0);
            $table->text('notes')->nullable();
            $table->timestampsTz();
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->foreignId('shipping_carrier_id')->nullable()->after('shipping_method_id')->constrained('shipping_carriers')->nullOnDelete();
            $table->string('carrier_name', 120)->nullable()->after('provider');
            $table->string('carrier_code', 80)->nullable()->after('carrier_name');
            $table->uuid('idempotency_key')->nullable()->unique()->after('carrier_code');
            $table->char('request_fingerprint', 64)->nullable()->after('idempotency_key');
            $table->foreignId('created_by')->nullable()->after('request_fingerprint')->constrained('users')->nullOnDelete();
            $table->foreignId('handed_over_by')->nullable()->after('shipped_at')->constrained('users')->nullOnDelete();
            $table->foreignId('delivered_by')->nullable()->after('delivered_at')->constrained('users')->nullOnDelete();
            $table->timestampTz('cancelled_at')->nullable()->after('delivered_by');
            $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable()->after('cancelled_by');
        });

        Schema::table('shipment_events', function (Blueprint $table) {
            $table->foreignId('actor_user_id')->nullable()->after('description')->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable()->after('actor_user_id');
        });

        Schema::create('shipment_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->uuid('operation_key')->unique();
            $table->string('action', 40);
            $table->char('payload_hash', 64);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at');
            $table->index(['shipment_id', 'action']);
        });

        $now = now();
        DB::table('shipping_carriers')->insert([
            ['name' => 'Manual carrier', 'code' => 'manual', 'integration_key' => 'manual', 'active' => true, 'position' => 0, 'notes' => 'Local manual fulfillment. No carrier API.', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'PostNL', 'code' => 'postnl', 'integration_key' => 'postnl', 'active' => false, 'position' => 10, 'notes' => 'Future adapter placeholder; no API credentials or live integration.', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'DHL', 'code' => 'dhl', 'integration_key' => 'dhl', 'active' => false, 'position' => 20, 'notes' => 'Future adapter placeholder; no API credentials or live integration.', 'created_at' => $now, 'updated_at' => $now],
        ]);

        $manualId = DB::table('shipping_carriers')->where('code', 'manual')->value('id');
        DB::table('shipments')->whereNull('shipping_carrier_id')->update([
            'shipping_carrier_id' => $manualId,
            'carrier_name' => 'Manual carrier',
            'carrier_code' => 'manual',
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_operations');
        Schema::table('shipment_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('actor_user_id');
            $table->dropColumn('metadata');
        });
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->dropConstrainedForeignId('shipping_carrier_id');
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('handed_over_by');
            $table->dropConstrainedForeignId('delivered_by');
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['carrier_name', 'carrier_code', 'idempotency_key', 'request_fingerprint', 'cancelled_at', 'cancellation_reason']);
        });
        Schema::dropIfExists('shipping_carriers');
    }
};
