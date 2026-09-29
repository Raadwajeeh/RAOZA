<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->text('fit_notes')->nullable()->after('description');
            $table->text('product_details')->nullable()->after('fit_notes');
            $table->text('care_instructions')->nullable()->after('product_details');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['fit_notes', 'product_details', 'care_instructions']);
        });
    }
};
