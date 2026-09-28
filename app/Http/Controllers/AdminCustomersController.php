<?php
namespace App\Http\Controllers;
use App\Domain\Commerce\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
class AdminCustomersController {
 public function index(Request $r){
  $q=Order::query()->select('customer_email',DB::raw('MAX(customer_phone) as customer_phone'),DB::raw('COUNT(*) as orders_count'),DB::raw("SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_orders_count"),DB::raw("COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END),0) as lifetime_value"),DB::raw('MAX(placed_at) as last_order_at'),DB::raw('MAX(user_id) as user_id'))->groupBy('customer_email')->orderByDesc('last_order_at');
  if($r->filled('q')){$term='%'.strtolower(trim($r->q)).'%';$q->whereRaw('LOWER(customer_email) LIKE ?',[$term]);}
  $customers=$q->paginate(30)->withQueryString();
  $emails=collect($customers->items())->pluck('customer_email');
  $latest=Order::query()->whereIn('customer_email',$emails)->with(['addresses'=>fn($x)=>$x->where('type','shipping'),'user:id,name,email,created_at'])->latest('placed_at')->get()->unique(fn($o)=>strtolower($o->customer_email))->keyBy(fn($o)=>strtolower($o->customer_email));
  $customers->through(function($customer)use($latest){$order=$latest->get(strtolower($customer->customer_email));$address=$order?->addresses->first();$customer->customer_name=$address?trim($address->first_name.' '.$address->last_name):null;$customer->account_name=$order?->user?->name;$customer->is_account=(bool)$customer->user_id;return $customer;});
  return Inertia::render('Admin/Customers/Index',['customers'=>$customers,'filters'=>['q'=>$r->string('q')->toString()]]);
 }

 public function show(string $email){
  $email=urldecode($email);
  $orders=Order::query()->whereRaw('LOWER(customer_email) = ?',[strtolower($email)])->with(['addresses','user:id,name,email,created_at'])->latest('placed_at')->get();
  abort_if($orders->isEmpty(),404);
  $latest=$orders->first();$shipping=$latest->addresses->firstWhere('type','shipping');
  $addresses=$orders->flatMap->addresses->map(fn($a)=>$a->only(['type','first_name','last_name','company','street','house_number','addition','postal_code','city','country_code']))->unique(fn($a)=>implode('|',$a))->values();
  return Inertia::render('Admin/Customers/Show',['customer'=>[
   'name'=>$shipping?trim($shipping->first_name.' '.$shipping->last_name):($latest->user?->name),
   'email'=>$latest->customer_email,'phone'=>$orders->pluck('customer_phone')->filter()->first(),
   'account'=>$latest->user?->only(['name','email','created_at']),'addresses'=>$addresses,
   'orders'=>$orders->map(fn($o)=>$o->only(['order_number','placed_at','payment_status','order_status','fulfillment_status','total_amount','currency'])),
   'paid_orders_count'=>$orders->filter(fn($o)=>$o->payment_status->value==='paid')->count(),'lifetime_value'=>$orders->filter(fn($o)=>$o->payment_status->value==='paid')->sum('total_amount'),
  ]]);
 }
}
