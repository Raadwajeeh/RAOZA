<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_number_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 24);
            $table->string('scope_key', 32);
            $table->unsignedBigInteger('next_value')->default(1);
            $table->timestampsTz();
            $table->unique(['document_type', 'scope_key']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('refund_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('original_invoice_id')->nullable()->constrained('invoices')->restrictOnDelete();
            $table->string('document_type', 24);
            $table->string('document_number', 64)->unique();
            $table->string('source_key', 100)->unique();
            $table->unsignedBigInteger('sequence_value');
            $table->string('sequence_scope', 32);
            $table->string('status', 24)->default('issued');
            $table->char('currency', 3);
            $table->json('seller_snapshot');
            $table->json('buyer_snapshot');
            $table->json('payment_snapshot')->nullable();
            $table->unsignedBigInteger('subtotal_amount')->default(0);
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedBigInteger('shipping_amount')->default(0);
            $table->unsignedBigInteger('net_amount');
            $table->unsignedBigInteger('tax_amount');
            $table->unsignedBigInteger('total_amount');
            $table->unsignedInteger('tax_rate_basis_points')->default(0);
            $table->timestampTz('issued_at');
            $table->string('pdf_path')->nullable()->unique();
            $table->char('pdf_sha256', 64)->nullable();
            $table->timestampTz('pdf_generated_at')->nullable();
            $table->timestampsTz();
            $table->index(['order_id', 'document_type', 'issued_at']);
            $table->index(['original_invoice_id', 'document_type']);
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_invoice_line_id')->nullable()->constrained('invoice_lines')->restrictOnDelete();
            $table->foreignId('source_order_item_id')->nullable()->constrained('order_items')->restrictOnDelete();
            $table->unsignedInteger('position');
            $table->string('line_type', 24);
            $table->string('description');
            $table->string('sku')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_amount');
            $table->unsignedBigInteger('subtotal_amount');
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedBigInteger('net_amount');
            $table->unsignedBigInteger('tax_amount');
            $table->unsignedBigInteger('total_amount');
            $table->unsignedInteger('tax_rate_basis_points')->default(0);
            $table->json('options_snapshot')->nullable();
            $table->json('product_snapshot')->nullable();
            $table->timestampsTz();
            $table->unique(['invoice_id', 'position']);
            $table->index(['source_invoice_line_id', 'invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('invoice_number_sequences');
    }
};
