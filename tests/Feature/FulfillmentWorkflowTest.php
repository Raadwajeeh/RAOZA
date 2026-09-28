<?php
namespace Tests\Feature;
use App\Domain\Commerce\Enums\FulfillmentStatus;
use App\Domain\Commerce\Enums\PaymentStatus;
use App\Domain\Commerce\Models\Order;
use App\Domain\Fulfillment\Services\FulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;
class FulfillmentWorkflowTest extends TestCase
{
 use RefreshDatabase;
 private function order(string $payment='paid',string $fulfillment='processing'):Order{return Order::create(['order_number'=>'RZ-TEST-'.uniqid(),'customer_email'=>'test@example.com','currency'=>'EUR','subtotal_amount'=>1000,'discount_amount'=>0,'shipping_amount'=>0,'tax_amount'=>174,'tax_rate_basis_points'=>2100,'total_amount'=>1000,'order_status'=>'confirmed','payment_status'=>$payment,'fulfillment_status'=>$fulfillment,'placed_at'=>now(),'paid_at'=>$payment==='paid'?now():null]);}
 public function test_paid_order_can_move_through_production_states():void{$s=app(FulfillmentService::class);$o=$this->order();$o=$s->transition($o,FulfillmentStatus::Printing);$this->assertSame(FulfillmentStatus::Printing,$o->fulfillment_status);$o=$s->transition($o,FulfillmentStatus::ReadyToShip);$this->assertSame(FulfillmentStatus::ReadyToShip,$o->fulfillment_status);$this->assertCount(2,$o->statusHistory);}
 public function test_unpaid_order_cannot_enter_fulfillment():void{$this->expectException(RuntimeException::class);app(FulfillmentService::class)->transition($this->order('pending','unfulfilled'),FulfillmentStatus::Processing);}
 public function test_on_hold_order_can_resume_to_supported_production_state():void{$o=$this->order('paid','on_hold');$o=app(FulfillmentService::class)->transition($o,FulfillmentStatus::Printing);$this->assertSame(FulfillmentStatus::Printing,$o->fulfillment_status);}
}
