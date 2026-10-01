<?php
namespace App\Http\Controllers;
use App\Domain\Admin\Services\AuditService;
use App\Domain\Marketing\Models\Discount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
class AdminDiscountsController {
 public function __construct(private AuditService $audit){}
 public function index(){
  $discounts=Discount::query()->latest()->get()->map(function(Discount $d){
   $paid=DB::table('discount_usages')->join('orders','orders.id','=','discount_usages.order_id')->where('discount_usages.discount_id',$d->id)->whereIn('orders.payment_status',['paid','partially_refunded','refunded']);
   $d->setAttribute('paid_uses',(clone $paid)->count());$d->setAttribute('discounted_amount',(int)(clone $paid)->sum('discount_usages.amount'));return $d;
  });
  return Inertia::render('Admin/Discounts/Index',['discounts'=>$discounts]);
 }
 public function store(Request $r){$d=$this->data($r);$d['code']=strtoupper(trim($d['code']));$this->assertCodeAvailable($d['code']);$m=Discount::create($d);$this->audit->record($r,'discount.created',$m,null,$m->toArray());return back()->with('success','Discount created.');}
 public function update(Request $r,Discount $discount){$before=$discount->toArray();$d=$this->data($r);$d['code']=strtoupper(trim($d['code']));$this->assertCodeAvailable($d['code'],$discount);$discount->update($d);$this->audit->record($r,'discount.updated',$discount,$before,$discount->fresh()->toArray());return back()->with('success','Discount updated.');}
 public function toggle(Request $r,Discount $discount){$before=$discount->toArray();$discount->update(['active'=>!$discount->active]);$this->audit->record($r,'discount.toggled',$discount,$before,$discount->fresh()->toArray());return back()->with('success',$discount->active?'Discount enabled.':'Discount disabled.');}
 private function data(Request $r):array{$d=$r->validate(['code'=>'required|string|max:50','name'=>'required|string|max:120','type'=>['required',Rule::in(['percentage','fixed'])],'value'=>'required|integer|min:1','minimum_order_amount'=>'nullable|integer|min:0','usage_limit'=>'nullable|integer|min:1','per_customer_limit'=>'nullable|integer|min:1','starts_at'=>'nullable|date','ends_at'=>'nullable|date|after:starts_at','active'=>'boolean']);if($d['type']==='percentage'&&$d['value']>100)throw ValidationException::withMessages(['value'=>'Percentage cannot exceed 100%.']);return $d;}
 private function assertCodeAvailable(string $code,?Discount $ignore=null):void{$q=Discount::query()->whereRaw('UPPER(code) = ?',[strtoupper($code)]);if($ignore)$q->whereKeyNot($ignore->id);if($q->exists())throw ValidationException::withMessages(['code'=>'This discount code already exists.']);}
}
