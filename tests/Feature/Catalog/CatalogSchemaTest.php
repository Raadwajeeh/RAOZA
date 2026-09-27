<?php
namespace Tests\Feature\Catalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
class CatalogSchemaTest extends TestCase
{
    use RefreshDatabase;
    public function test_catalog_tables_exist(): void
    {
        foreach (['products','product_options','product_option_values','product_variants','variant_option_values','categories','category_product','collections','collection_product'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing catalog table: {$table}");
        }
    }
}
