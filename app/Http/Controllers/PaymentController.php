<?php
namespace App\Http\Controllers;
use App\Domain\Commerce\Models\Order;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Services\PaymentService;
use App\Domain\Payments\Services\PaymentSyncCoordinator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
class PaymentController extends Controller {
 public function start(Request $request,Order $order,PaymentService $service):RedirectResponse {
  abort_unless($request->session()->get('last_order_number')===$order->order_number,404); if(!$order->payment_status->canAcceptPayment())return redirect()->route('checkout.confirmation',$order->order_number);
  $payment=$service->createAttempt($order,route('payments.return',['order'=>$order->order_number]),route('payments.webhook'));
  $url=$payment->metadata['checkout_url']??null; if(!$url)return back()->withErrors(['payment'=>'The payment provider did not return a checkout URL.']); return redirect()->away($url);
 }
 public function returned(Request $request,Order $order,PaymentSyncCoordinator $coordinator):RedirectResponse {
  abort_unless($request->session()->get('last_order_number')===$order->order_number,404); $payment=$order->payments()->whereNotNull('provider_payment_id')->latest('id')->first(); if($payment){try{$coordinator->sync($payment,'customer_return');}catch(\Throwable $e){Log::warning('Payment return sync failed',['order'=>$order->order_number,'payment'=>$payment->id,'exception_type'=>$e::class]);}} return redirect()->route('checkout.confirmation',$order->order_number);
 }
 public function webhook(Request $request,PaymentSyncCoordinator $coordinator):\Illuminate\Http\Response {
  $providerId=(string)$request->input('id',''); if($providerId==='')return response('',200); $payment=Payment::where('provider','mollie')->where('provider_payment_id',$providerId)->first(); if(!$payment)return response('',200); try{$coordinator->sync($payment,'webhook');}catch(\Throwable $e){Log::error('Payment webhook sync failed',['payment'=>$payment->id,'exception_type'=>$e::class]); return response('',500);} return response('',200);
 }
}
