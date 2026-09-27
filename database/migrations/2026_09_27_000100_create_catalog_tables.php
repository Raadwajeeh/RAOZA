<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('status', 24)->default('draft')->index();
            $table->unsignedBigInteger('base_price'); // EUR minor units (cents)
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->timestampTz('published_at')->nullable()->index();
            $table->timestampsTz();
        });

        Schema::create('product_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestampsTz();
            $table->unique(['product_id', 'name']);
            $table->index(['product_id', 'position']);
        });

        Schema::create('product_option_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_option_id')->constrained()->cascadeOnDelete();
            $table->string('value');
            $table->jsonb('metadata')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestampsTz();
            $table->unique(['product_option_id', 'value']);
            $table->index(['product_option_id', 'position']);
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku')->unique();
            $table->unsignedBigInteger('price_override')->nullable(); // EUR minor units
            $table->string('status', 24)->default('active')->index();
            $table->string('option_signature')->nullable();
            $table->timestampsTz();
            $table->unique(['product_id', 'option_signature']);
            $table->index(['product_id', 'status']);
        });

        Schema::create('variant_option_values', function (Blueprint $table) {
            $table->foreignId('variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('option_value_id')->constrained('product_option_values')->restrictOnDelete();
            $table->primary(['variant_id', 'option_value_id']);
            $table->index('option_value_id');
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status', 24)->default('active')->index();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestampsTz();
            $table->index(['parent_id', 'position']);
        });

        Schema::create('category_product', function (Blueprint $table) {
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->primary(['category_id', 'product_id']);
            $table->index(['product_id', 'position']);
        });

        Schema::create('collections', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('status', 24)->default('draft')->index();
            $table->timestampTz('published_at')->nullable()->index();
            $table->timestampsTz();
        });

        Schema::create('collection_product', function (Blueprint $table) {
            $table->foreignId('collection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->primary(['collection_id', 'product_id']);
            $table->index(['product_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_product');
        Schema::dropIfExists('collections');
        Schema::dropIfExists('category_product');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('variant_option_values');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('product_option_values');
        Schema::dropIfExists('product_options');
        Schema::dropIfExists('products');
    }
};
