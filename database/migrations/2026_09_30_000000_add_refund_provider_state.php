<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refunds', function (Blueprint $table): void {
            $table->string('provider_status', 32)->nullable()->after('provider_refund_id')->index();
            $table->timestampTz('submission_started_at')->nullable()->after('requested_at');
            $table->timestampTz('last_synced_at')->nullable()->after('submission_started_at');
            $table->unique('provider_refund_id', 'refunds_provider_refund_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $table): void {
            $table->dropUnique('refunds_provider_refund_id_unique');
            $table->dropIndex(['provider_status']);
            $table->dropColumn(['provider_status', 'submission_started_at', 'last_synced_at']);
        });
    }
};
