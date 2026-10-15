<?php

namespace App\Domain\Payments\Services;

use App\Domain\Commerce\Models\Order;
use App\Domain\Marketing\Services\AnalyticsService;
use App\Domain\Invoicing\Services\AccountingDocumentService;
use App\Domain\Payments\Enums\PaymentAttemptStatus;
use App\Domain\Payments\Data\PaymentSyncResult;
use App\Domain\Payments\Models\Payment;
use App\Mail\PaymentConfirmedMail;
use App\Services\TransactionalNotificationService;

class PaymentSyncCoordinator
{
    public function __construct(
        private PaymentService $payments,
        private AnalyticsService $analytics,
        private AccountingDocumentService $accountingDocuments,
        private TransactionalNotificationService $notifications,
    ) {}

    public function sync(Payment $payment, string $eventType): PaymentSyncResult
    {
        $result = $this->payments->sync($payment, $eventType);

        $order = Order::query()->findOrFail($payment->order_id);
        if ($result->becamePaid) {
            $this->notifications->queueOnce('payment.confirmed:'.$result->payment->id, $order->customer_email, new PaymentConfirmedMail($order), $result->payment);
            $this->analytics->recordVerifiedPurchase($order);
        }
        if ($result->payment->status === PaymentAttemptStatus::Paid) {
            // Idempotent and fail-closed while the accounting policy is unapproved.
            $this->accountingDocuments->issueInvoiceForPaidOrder($order, $result->payment);
        }

        return $result;
    }
}
