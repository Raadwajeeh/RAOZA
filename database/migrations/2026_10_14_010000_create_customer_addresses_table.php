<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('phone', 40)->nullable()->after('email');
        });

        Schema::create('customer_addresses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label', 80)->nullable();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('company', 150)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('street', 150);
            $table->string('house_number', 32);
            $table->string('addition', 32)->nullable();
            $table->string('postal_code', 24);
            $table->string('city', 120);
            $table->char('country_code', 2)->default('NL');
            $table->boolean('is_shipping')->default(true);
            $table->boolean('is_billing')->default(false);
            $table->boolean('is_default_shipping')->default(false);
            $table->boolean('is_default_billing')->default(false);
            $table->timestamps();
            $table->index(['user_id', 'is_shipping', 'is_default_shipping'], 'customer_addresses_shipping_default_idx');
            $table->index(['user_id', 'is_billing', 'is_default_billing'], 'customer_addresses_billing_default_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('phone');
        });
    }
};
