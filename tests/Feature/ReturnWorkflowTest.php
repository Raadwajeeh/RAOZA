<?php
namespace Tests\Feature;
use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Enums\VariantStatus;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Commerce\Models\Order;
use App\Domain\Inventory\Models\Inventory;
use App\Domain\Returns\Enums\ReturnCondition;
use App\Domain\Returns\Enums\ReturnResolution;
use App\Domain\Returns\Enums\ReturnStatus;
use App\Domain\Returns\Services\ReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;
class ReturnWorkflowTest extends TestCase {
 use RefreshDatabase;
 private function order(string $fulfillment='fulfilled'):Order{return Order::create(['order_number'=>'RZ-RET-'.uniqid(),'customer_email'=>'return@example.com','currency'=>'EUR','subtotal_amount'=>1000,'discount_amount'=>0,'shipping_amount'=>0,'tax_amount'=>174,'tax_rate_basis_points'=>2100,'total_amount'=>1000,'order_status'=>'confirmed','payment_status'=>'paid','fulfillment_status'=>$fulfillment,'placed_at'=>now(),'paid_at'=>now()]);}
 private function returnWithInventory(string $sku):array {
  $product=Product::create(['name'=>'Return Test Tee','slug'=>'return-test-'.strtolower($sku),'status'=>ProductStatus::Draft,'base_price'=>1000]);
  $variant=ProductVariant::create(['product_id'=>$product->id,'sku'=>$sku,'status'=>VariantStatus::Active]);
  $inventory=Inventory::create(['variant_id'=>$variant->id,'quantity_on_hand'=>4,'quantity_reserved'=>0]);
  $order=$this->order();
  $item=$order->items()->create(['product_id'=>$product->id,'variant_id'=>$variant->id,'product_name'=>$product->name,'sku'=>$sku,'unit_price'=>1000,'quantity'=>1,'subtotal_amount'=>1000,'discount_amount'=>0,'tax_amount'=>174,'total_amount'=>1000]);
  $service=app(ReturnService::class);
  $return=$service->create($order,[['order_item_id'=>$item->id,'quantity'=>1,'reason_code'=>'other']]);
  $service->transition($return,ReturnStatus::Approved);
  $service->transition($return->fresh(),ReturnStatus::Received);
  return [$service,$return->fresh(),$return->items()->firstOrFail(),$inventory];
 }
 public function test_physical_return_requires_fulfilled_order():void{$this->expectException(RuntimeException::class);app(ReturnService::class)->create($this->order('processing'),[['order_item_id'=>1,'quantity'=>1,'reason_code'=>'other']]);}
 public function test_return_requires_at_least_one_item():void{$this->expectException(RuntimeException::class);app(ReturnService::class)->create($this->order(),[]);}
 public function test_resellable_return_restock_is_idempotent():void {
  [$service,$return,$item,$inventory]=$this->returnWithInventory('RZ-RETURN-RESELLABLE');
  $service->inspect($item,ReturnCondition::Resellable,ReturnResolution::Refund);
  $service->inspect($item->fresh(),ReturnCondition::Resellable,ReturnResolution::Refund);
  $this->assertSame(5,$inventory->fresh()->quantity_on_hand);
  $this->assertDatabaseCount('inventory_movements',1);
  $service->transition($return->fresh(),ReturnStatus::Inspected);
  $this->assertSame(ReturnStatus::Inspected,$return->fresh()->status);
 }
 public function test_damaged_return_does_not_restore_inventory():void {
  [$service,$return,$item,$inventory]=$this->returnWithInventory('RZ-RETURN-DAMAGED');
  $service->inspect($item,ReturnCondition::Damaged,ReturnResolution::Refund);
  $this->assertSame(4,$inventory->fresh()->quantity_on_hand);
  $this->assertDatabaseCount('inventory_movements',0);
  $service->transition($return->fresh(),ReturnStatus::Inspected);
  $this->assertSame(ReturnStatus::Inspected,$return->fresh()->status);
 }
}
