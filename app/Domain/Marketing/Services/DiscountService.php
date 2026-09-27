<?php
namespace App\Domain\Marketing\Services;
use App\Domain\Marketing\Models\Discount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class DiscountService {
 public function quote(?string $code,int $subtotal,string $email):array {
  $code=strtoupper(trim((string)$code)); if($code==='')return ['discount'=>null,'amount'=>0,'code'=>null];
  $discount=Discount::query()->whereRaw('UPPER(code) = ?',[$code])->first();
  if(!$discount||!$discount->active)throw ValidationException::withMessages(['discount_code'=>'This discount code is not valid.']);
  $now=now(); if(($discount->starts_at&&$discount->starts_at->isFuture())||($discount->ends_at&&$discount->ends_at->isPast()))throw ValidationException::withMessages(['discount_code'=>'This discount code is not currently active.']);
  if($discount->minimum_order_amount!==null&&$subtotal<$discount->minimum_order_amount)throw ValidationException::withMessages(['discount_code'=>'The order does not meet this code’s minimum amount.']);
  if($discount->usage_limit!==null&&DB::table('discount_usages')->join('orders','orders.id','=','discount_usages.order_id')->where('discount_usages.discount_id',$discount->id)->where('orders.payment_status','paid')->count()>=$discount->usage_limit)throw ValidationException::withMessages(['discount_code'=>'This discount code has reached its usage limit.']);
  if($discount->per_customer_limit!==null&&DB::table('discount_usages')->join('orders','orders.id','=','discount_usages.order_id')->where('discount_usages.discount_id',$discount->id)->whereRaw('LOWER(discount_usages.customer_email) = ?', [strtolower($email)])->where('orders.payment_status','paid')->count()>=$discount->per_customer_limit)throw ValidationException::withMessages(['discount_code'=>'This discount code has already been used the maximum number of times for this email.']);
  $amount=$discount->type==='percentage'?(int)floor($subtotal*min($discount->value,100)/100):min($discount->value,$subtotal);
  return ['discount'=>$discount,'amount'=>max(0,$amount),'code'=>$discount->code];
 }
}
