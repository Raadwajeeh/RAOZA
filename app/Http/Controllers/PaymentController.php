<?php
namespace App\Http\Controllers;
use App\Domain\Commerce\Enums\PaymentStatus;
use App\Domain\Commerce\Models\Order;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\PaymentConfirmedMail;
use App\Domain\Marketing\Services\AnalyticsService;
class PaymentController extends Controller {
 public function start(Request $request,Order $order,PaymentService $service):RedirectResponse {
  abort_unless($request->session()->get('last_order_number')===$order->order_number,404); if($order->payment_status===PaymentStatus::Paid)return redirect()->route('checkout.confirmation',$order->order_number);
  $payment=$service->createAttempt($order,route('payments.return',['order'=>$order->order_number]),route('payments.webhook'));
  $url=$payment->metadata['checkout_url']??null; if(!$url)return back()->withErrors(['payment'=>'The payment provider did not return a checkout URL.']); return redirect()->away($url);
 }
 public function returned(Request $request,Order $order,PaymentService $service,AnalyticsService $analytics):RedirectResponse {
  abort_unless($request->session()->get('last_order_number')===$order->order_number,404); $payment=$order->payments()->whereNotNull('provider_payment_id')->latest('id')->first(); if($payment){try{$wasPaid=$order->payment_status===PaymentStatus::Paid;$service->sync($payment,'customer_return');$order->refresh();if(!$wasPaid&&$order->payment_status===PaymentStatus::Paid){Mail::to($order->customer_email)->queue(new PaymentConfirmedMail($order));$analytics->recordVerifiedPurchase($order);}}catch(\Throwable $e){Log::warning('Payment return sync failed',['order'=>$order->order_number,'payment'=>$payment->id,'exception'=>$e->getMessage()]);}} return redirect()->route('checkout.confirmation',$order->order_number);
 }
 public function webhook(Request $request,PaymentService $service,AnalyticsService $analytics):\Illuminate\Http\Response {
  $providerId=(string)$request->input('id',''); if($providerId==='')return response('',200); $payment=Payment::where('provider','mollie')->where('provider_payment_id',$providerId)->first(); if(!$payment)return response('',200); try{$order=$payment->order;$wasPaid=$order->payment_status===PaymentStatus::Paid;$service->sync($payment,'webhook');$order->refresh();if(!$wasPaid&&$order->payment_status===PaymentStatus::Paid){Mail::to($order->customer_email)->queue(new PaymentConfirmedMail($order));$analytics->recordVerifiedPurchase($order);}}catch(\Throwable $e){Log::error('Payment webhook sync failed',['payment'=>$payment->id,'exception'=>$e->getMessage()]); return response('',500);} return response('',200);
 }
}
