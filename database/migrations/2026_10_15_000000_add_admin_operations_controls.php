<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestampTz('staff_disabled_at')->nullable()->after('role')->index();
            $table->foreignId('staff_disabled_by')->nullable()->after('staff_disabled_at')->constrained('users')->nullOnDelete();
        });

        Schema::create('notification_dispatches', function (Blueprint $table) {
            $table->id();
            $table->string('dispatch_key', 191)->unique();
            $table->string('type', 191)->index();
            $table->char('recipient_hash', 64);
            $table->string('subject_type', 120)->nullable();
            $table->string('subject_id', 100)->nullable();
            $table->timestampTz('queued_at');
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_dispatches');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('staff_disabled_by');
            $table->dropIndex(['staff_disabled_at']);
            $table->dropColumn('staff_disabled_at');
        });
    }
};
