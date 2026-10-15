<?php

namespace App\Domain\Payments\Services;

use App\Domain\Commerce\Models\Order;
use App\Domain\Marketing\Services\AnalyticsService;
use App\Domain\Invoicing\Services\AccountingDocumentService;
use App\Domain\Payments\Enums\PaymentAttemptStatus;
use App\Domain\Payments\Data\PaymentSyncResult;
use App\Domain\Payments\Models\Payment;
use App\Mail\PaymentConfirmedMail;
use Illuminate\Support\Facades\Mail;

class PaymentSyncCoordinator
{
    public function __construct(
        private PaymentService $payments,
        private AnalyticsService $analytics,
        private AccountingDocumentService $accountingDocuments,
    ) {}

    public function sync(Payment $payment, string $eventType): PaymentSyncResult
    {
        $result = $this->payments->sync($payment, $eventType);

        $order = Order::query()->findOrFail($payment->order_id);
        if ($result->becamePaid) {
            Mail::to($order->customer_email)->queue(new PaymentConfirmedMail($order));
            $this->analytics->recordVerifiedPurchase($order);
        }
        if ($result->payment->status === PaymentAttemptStatus::Paid) {
            // Idempotent and fail-closed while the accounting policy is unapproved.
            $this->accountingDocuments->issueInvoiceForPaidOrder($order, $result->payment);
        }

        return $result;
    }
}
