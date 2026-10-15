<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('launch_approvals', function (Blueprint $table) {
            $table->id();
            $table->string('category', 80)->index();
            $table->string('decision_key', 160)->index();
            $table->string('required_role', 40);
            $table->string('status', 24)->default('pending')->index();
            $table->char('version_hash', 64);
            $table->text('notes')->nullable();
            $table->string('evidence_reference', 500)->nullable();
            $table->string('approver_identity')->nullable();
            $table->string('approver_role', 80)->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('decided_at')->nullable();
            $table->timestampsTz();
            $table->unique(['decision_key', 'version_hash']);
        });

        Schema::table('content_pages', function (Blueprint $table) {
            $table->jsonb('draft_payload')->nullable();
            $table->char('draft_hash', 64)->nullable()->index();
        });

        Schema::table('shipping_methods', function (Blueprint $table) {
            $table->jsonb('pending_configuration')->nullable();
            $table->char('pending_hash', 64)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('shipping_methods', fn (Blueprint $table) => $table->dropColumn(['pending_configuration', 'pending_hash']));
        Schema::table('content_pages', fn (Blueprint $table) => $table->dropColumn(['draft_payload', 'draft_hash']));
        Schema::dropIfExists('launch_approvals');
    }
};
