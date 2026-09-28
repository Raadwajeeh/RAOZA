<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->text('description')->nullable()->after('slug');
            $table->string('seo_title')->nullable()->after('description');
            $table->text('seo_description')->nullable()->after('seo_title');
            $table->string('canonical_url')->nullable()->after('seo_description');
            $table->boolean('indexable')->default(true)->after('canonical_url');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['description', 'seo_title', 'seo_description', 'canonical_url', 'indexable']);
        });
    }
};
