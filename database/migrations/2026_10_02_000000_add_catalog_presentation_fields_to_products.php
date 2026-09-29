<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('short_description', 500)->nullable()->after('description');
            $table->unsignedInteger('position')->default(0)->after('status')->index();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['position']);
            $table->dropColumn(['short_description', 'position']);
        });
    }
};
