<?php
namespace Tests\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
class FulfillmentSchemaTest extends TestCase {use RefreshDatabase;public function test_fulfillment_tables_exist():void{foreach(['shipping_methods','shipping_carriers','shipments','shipment_items','shipment_events','shipment_operations'] as $table)$this->assertTrue(Schema::hasTable($table));}}
