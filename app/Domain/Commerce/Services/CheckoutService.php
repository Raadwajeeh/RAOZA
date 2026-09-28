<?php
namespace App\Domain\Commerce\Services;
use App\Domain\Catalog\Enums\{ProductStatus,VariantStatus};
use App\Domain\Catalog\Models\{Product,ProductVariant};
use App\Domain\Commerce\Enums\{CartStatus,FulfillmentStatus,OrderStatus,PaymentStatus};
use App\Domain\Commerce\Models\{Cart,Order};
use App\Domain\Fulfillment\Services\ShippingQuoteService;
use App\Domain\Inventory\Models\Inventory;
use App\Domain\Marketing\Services\DiscountService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
class CheckoutService {
 public function __construct(private ShippingQuoteService $shippingQuotes,private DiscountService $discounts,private MoneyService $money){}
 public function createOrder(Cart $cart,array $data):Order {
  return DB::transaction(function()use($cart,$data){
   $lockedCart=Cart::query()->whereKey($cart->id)->lockForUpdate()->firstOrFail();
   if($lockedCart->status===CartStatus::Converted){$existing=Order::query()->where('source_cart_id',$lockedCart->id)->first();if($existing)return $existing;}
   if($lockedCart->status!==CartStatus::Active)throw ValidationException::withMessages(['cart'=>'This bag can no longer be checked out.']);
   $items=$lockedCart->items()->orderBy('variant_id')->get();if($items->isEmpty())throw ValidationException::withMessages(['cart'=>'Your bag is empty.']);
   $prepared=[];$subtotal=0;
   foreach($items as $item){$variant=ProductVariant::query()->whereKey($item->variant_id)->lockForUpdate()->firstOrFail();$product=Product::query()->whereKey($variant->product_id)->lockForUpdate()->firstOrFail();$variant->setRelation('product',$product);$variant->load('optionValues.option');$published=$product->status===ProductStatus::Active&&(!$product->published_at||$product->published_at->isPast());if(!$published||$variant->status!==VariantStatus::Active)throw ValidationException::withMessages(['cart'=>'One or more items are no longer available.']);$inventory=Inventory::query()->where('variant_id',$variant->id)->lockForUpdate()->first();if(!$inventory||$inventory->availableQuantity()<$item->quantity)throw ValidationException::withMessages(['cart'=>"Not enough stock remains for {$product->name}."]);$unit=$variant->effectivePrice();$line=$unit*$item->quantity;$subtotal+=$line;$prepared[]=compact('item','variant','product','inventory','unit','line');}
   $shippingMethod=$this->shippingQuotes->resolve((int)$data['shipping_method_id']);
   if(strtoupper($shippingMethod->currency)!==strtoupper($lockedCart->currency))throw ValidationException::withMessages(['shipping_method_id'=>'The selected shipping method is not available for this currency.']);
   $discountQuote=$this->discounts->quote($data['discount_code']??null,$subtotal,(string)$data['email']);$discount=$discountQuote['amount'];$shipping=$shippingMethod->price;
   $taxRate=(int)config('commerce.vat_rate_basis_points',2100);$merchandiseTotal=max(0,$subtotal-$discount);$total=$merchandiseTotal+$shipping;
   $lineDiscounts=$this->money->allocate($discount,array_column($prepared,'line'));
   $lineTotals=array_map(fn(array $row,int $index):int=>$row['line']-$lineDiscounts[$index],$prepared,array_keys($prepared));
   $merchandiseTax=$this->money->extractIncludedTax($merchandiseTotal,$taxRate);$shippingTax=$this->money->extractIncludedTax($shipping,$taxRate);$tax=$merchandiseTax+$shippingTax;
   $lineTaxes=$this->money->allocate($merchandiseTax,$lineTotals);
   $order=Order::query()->create(['order_number'=>$this->nextOrderNumber(),'user_id'=>$lockedCart->user_id,'source_cart_id'=>$lockedCart->id,'shipping_method_id'=>$shippingMethod->id,'shipping_method_name'=>$shippingMethod->name,'customer_email'=>Str::lower($data['email']),'customer_phone'=>($data['phone']??null)?:null,'analytics_consent'=>(bool)($data['_analytics_consent']??false),'marketing_consent'=>(bool)($data['_marketing_consent']??false),'currency'=>$lockedCart->currency,'discount_code'=>$discountQuote['code'],'subtotal_amount'=>$subtotal,'discount_amount'=>$discount,'shipping_amount'=>$shipping,'shipping_tax_amount'=>$shippingTax,'tax_amount'=>$tax,'tax_rate_basis_points'=>$taxRate,'total_amount'=>$total,'order_status'=>OrderStatus::PendingPayment,'payment_status'=>PaymentStatus::Unpaid,'fulfillment_status'=>FulfillmentStatus::Unfulfilled,'placed_at'=>now()]);
   foreach($prepared as $index=>$row){$options=$row['variant']->optionValues->mapWithKeys(fn($v)=>[$v->option->name=>$v->value])->all();$lineDiscount=$lineDiscounts[$index];$lineTax=$lineTaxes[$index];$order->items()->create(['product_id'=>$row['product']->id,'variant_id'=>$row['variant']->id,'product_name'=>$row['product']->name,'variant_options'=>$options,'sku'=>$row['variant']->sku,'unit_price'=>$row['unit'],'quantity'=>$row['item']->quantity,'subtotal_amount'=>$row['line'],'discount_amount'=>$lineDiscount,'tax_amount'=>$lineTax,'total_amount'=>$row['line']-$lineDiscount,'product_snapshot'=>['slug'=>$row['product']->slug,'name'=>$row['product']->name,'options'=>$options]]);$row['inventory']->quantity_reserved+=$row['item']->quantity;$row['inventory']->assertConsistent();$row['inventory']->save();}
   if($discountQuote['discount'])DB::table('discount_usages')->insert(['discount_id'=>$discountQuote['discount']->id,'order_id'=>$order->id,'customer_email'=>Str::lower($data['email']),'amount'=>$discount,'created_at'=>now()]);
   $shippingAddress=$this->addressPayload($data,'shipping');$order->addresses()->create($shippingAddress);$order->addresses()->create($data['billing_same_as_shipping']?[...$shippingAddress,'type'=>'billing']:$this->addressPayload($data,'billing'));
   $order->statusHistory()->create(['domain'=>'order','from_status'=>null,'to_status'=>OrderStatus::PendingPayment->value,'reason'=>'checkout_created','actor_user_id'=>$lockedCart->user_id,'created_at'=>now()]);$lockedCart->update(['status'=>CartStatus::Converted]);return $order->load(['items','addresses']);
  },3);
 }
 private function addressPayload(array $data,string $type):array{$p=$type.'_';return ['type'=>$type,'first_name'=>$data[$p.'first_name'],'last_name'=>$data[$p.'last_name'],'company'=>($data[$p.'company']??null)?:null,'street'=>$data[$p.'street'],'house_number'=>$data[$p.'house_number'],'addition'=>($data[$p.'addition']??null)?:null,'postal_code'=>strtoupper(trim($data[$p.'postal_code'])),'city'=>$data[$p.'city'],'country_code'=>strtoupper($data[$p.'country_code'])];}
 private function nextOrderNumber():string{do{$n='RZ-'.now()->format('Ymd').'-'.strtoupper(Str::random(8));}while(Order::query()->where('order_number',$n)->exists());return $n;}
}
