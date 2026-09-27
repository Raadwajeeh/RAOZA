<?php
namespace App\Http\Controllers;
use App\Domain\Payments\Models\Payment;use Illuminate\Http\{RedirectResponse,Request};use Inertia\{Inertia,Response};
class DemoPaymentController extends Controller {
 private function guard():void{abort_unless(app()->environment(['local','testing'])&&config('commerce.demo_mode'),404);}
 public function show(Request $request,string $providerPaymentId):Response{$this->guard();$payment=Payment::with('order')->where('provider','demo')->where('provider_payment_id',$providerPaymentId)->firstOrFail();abort_unless($request->session()->get('last_order_number')===$payment->order->order_number,404);return Inertia::render('Demo/Payment',['payment'=>['id'=>$providerPaymentId,'orderNumber'=>$payment->order->order_number,'amount'=>$payment->amount,'currency'=>$payment->currency]]);}
 public function complete(Request $request,string $providerPaymentId):RedirectResponse{$this->guard();$data=$request->validate(['status'=>'required|in:paid,failed,cancelled']);$payment=Payment::with('order')->where('provider','demo')->where('provider_payment_id',$providerPaymentId)->firstOrFail();abort_unless($request->session()->get('last_order_number')===$payment->order->order_number,404);$meta=$payment->metadata??[];$meta['demo_status']=$data['status'];$payment->update(['metadata'=>$meta]);return redirect()->route('payments.return',['order'=>$payment->order->order_number]);}
}
