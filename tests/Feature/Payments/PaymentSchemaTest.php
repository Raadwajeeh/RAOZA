<?php
namespace Tests\Feature\Payments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
class PaymentSchemaTest extends TestCase { use RefreshDatabase; public function test_payment_tables_exist():void { $this->assertTrue(Schema::hasColumns('payments',['order_id','provider','provider_payment_id','currency','amount','status','metadata'])); $this->assertTrue(Schema::hasColumns('payment_events',['payment_id','event_key','event_type','provider_status','processed_at'])); } }
