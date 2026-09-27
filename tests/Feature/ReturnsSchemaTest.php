<?php
namespace Tests\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
class ReturnsSchemaTest extends TestCase {use RefreshDatabase;public function test_returns_refunds_schema_exists():void {foreach(['returns','return_items','refunds'] as $table)$this->assertTrue(Schema::hasTable($table));$this->assertTrue(Schema::hasColumns('return_items',['condition','resolution','restocked_at']));$this->assertTrue(Schema::hasColumns('refunds',['payment_id','order_id','return_id','amount','status','idempotency_key']));}}
