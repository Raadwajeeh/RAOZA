<?php

namespace App\Domain\Payments\Services;

use App\Domain\Commerce\Models\Order;
use App\Domain\Marketing\Services\AnalyticsService;
use App\Domain\Payments\Data\PaymentSyncResult;
use App\Domain\Payments\Models\Payment;
use App\Mail\PaymentConfirmedMail;
use Illuminate\Support\Facades\Mail;

class PaymentSyncCoordinator
{
    public function __construct(private PaymentService $payments, private AnalyticsService $analytics) {}

    public function sync(Payment $payment, string $eventType): PaymentSyncResult
    {
        $result = $this->payments->sync($payment, $eventType);

        if ($result->becamePaid) {
            $order = Order::query()->findOrFail($payment->order_id);
            Mail::to($order->customer_email)->queue(new PaymentConfirmedMail($order));
            $this->analytics->recordVerifiedPurchase($order);
        }

        return $result;
    }
}
