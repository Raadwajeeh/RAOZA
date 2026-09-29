<?php

namespace App\Http\Controllers;

use App\Domain\Commerce\Models\Order;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Services\PaymentService;
use App\Domain\Payments\Services\PaymentSyncCoordinator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaymentController extends Controller
{
    public function start(Request $request, Order $order, PaymentService $service): RedirectResponse
    {
        abort_unless($request->session()->get('last_order_number') === $order->order_number, 404);

        if (! $order->payment_status->canAcceptPayment()) {
            return redirect()->route('checkout.confirmation', $order->order_number);
        }

        $payment = $service->createAttempt(
            $order,
            route('payments.return', ['order' => $order->order_number]),
            route('payments.webhook'),
        );
        $url = $payment->metadata['checkout_url'] ?? null;

        if (! $url) {
            return back()->withErrors(['payment' => 'The payment provider did not return a checkout URL.']);
        }

        return redirect()->away($url);
    }

    public function returned(Request $request, Order $order, PaymentSyncCoordinator $coordinator): RedirectResponse
    {
        abort_unless($request->session()->get('last_order_number') === $order->order_number, 404);

        $payment = $order->payments()->whereNotNull('provider_payment_id')->latest('id')->first();

        if ($payment) {
            try {
                $coordinator->sync($payment, 'customer_return');
            } catch (Throwable $exception) {
                Log::warning('Payment return sync failed', [
                    'operation' => 'payment_return_sync',
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'payment_id' => $payment->id,
                    'provider_payment_id' => $payment->provider_payment_id,
                    'exception_type' => $exception::class,
                ]);
            }
        }

        return redirect()->route('checkout.confirmation', $order->order_number);
    }

    public function webhook(Request $request, PaymentSyncCoordinator $coordinator): Response
    {
        $providerId = (string) $request->input('id', '');

        if ($providerId === '') {
            return response('', 200);
        }

        $payment = Payment::query()
            ->where('provider', 'mollie')
            ->where('provider_payment_id', $providerId)
            ->first();

        if (! $payment) {
            return response('', 200);
        }

        try {
            $coordinator->sync($payment, 'webhook');
        } catch (Throwable $exception) {
            Log::error('Payment webhook sync failed', [
                'operation' => 'payment_webhook_sync',
                'order_id' => $payment->order_id,
                'payment_id' => $payment->id,
                'provider_payment_id' => $payment->provider_payment_id,
                'exception_type' => $exception::class,
            ]);

            return response('', 500);
        }

        return response('', 200);
    }
}
