<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('notification_dispatches', function (Blueprint $table) {
            $table->longText('encrypted_recipient')->nullable()->after('recipient_hash');
            $table->longText('encrypted_mailable')->nullable()->after('encrypted_recipient');
            $table->string('status', 24)->default('pending')->after('subject_id')->index();
            $table->unsignedSmallInteger('attempts')->default(0)->after('status');
            $table->timestampTz('claimed_at')->nullable()->after('attempts');
            $table->timestampTz('next_attempt_at')->nullable()->after('claimed_at')->index();
            $table->timestampTz('sent_at')->nullable()->after('queued_at');
            $table->timestampTz('failed_at')->nullable()->after('sent_at');
            $table->string('last_error_type', 191)->nullable()->after('failed_at');
        });
    }
    public function down(): void
    {
        Schema::table('notification_dispatches', function (Blueprint $table) {
            $table->dropIndex(['status']); $table->dropIndex(['next_attempt_at']);
            $table->dropColumn(['encrypted_recipient','encrypted_mailable','status','attempts','claimed_at','next_attempt_at','sent_at','failed_at','last_error_type']);
        });
    }
};
