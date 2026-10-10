<?php

namespace App\Domain\Commerce\Services;

use App\Domain\Commerce\Enums\PaymentStatus;
use App\Domain\Payments\Contracts\PaymentProvider;
use App\Domain\Payments\Enums\PaymentAttemptStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Services\PaymentService;
use App\Domain\Payments\Services\PaymentSyncCoordinator;
use App\Domain\Returns\Enums\RefundStatus;
use App\Domain\Returns\Models\Refund;
use App\Domain\Returns\Services\RefundService;
use Illuminate\Support\Facades\Log;
use Throwable;

class CommerceReconciliationService
{
    public function __construct(
        private PaymentProvider $provider,
        private PaymentSyncCoordinator $payments,
        private PaymentService $paymentCreation,
        private RefundService $refunds,
    ) {}

    /** @return array{payments_checked:int,payments_changed:int,refunds_checked:int,refunds_changed:int,failures:int} */
    public function reconcile(int $limit = 50): array
    {
        $limit = max(1, min($limit, 200));
        $summary = ['payments_checked' => 0, 'payments_changed' => 0, 'refunds_checked' => 0, 'refunds_changed' => 0, 'failures' => 0];

        $uncertainIds = Payment::query()
            ->where('provider', $this->provider->name())
            ->whereNull('provider_payment_id')
            ->where(function ($query): void {
                $query->whereIn('status', [PaymentAttemptStatus::Creating->value, PaymentAttemptStatus::Created->value]);
            })
            ->whereHas('order', fn ($query) => $query->whereIn('payment_status', [
                PaymentStatus::Unpaid->value,
                PaymentStatus::Pending->value,
                PaymentStatus::Failed->value,
            ]))
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        foreach ($uncertainIds as $paymentId) {
            $summary['payments_checked']++;
            try {
                $payment = Payment::query()->with('order')->findOrFail($paymentId);
                $recovered = $this->paymentCreation->createAttempt(
                    $payment->order,
                    route('payments.return', ['order' => $payment->order->order_number]),
                    route('payments.webhook'),
                );
                if ($recovered->provider_payment_id !== null) {
                    $summary['payments_changed']++;
                }
            } catch (Throwable $exception) {
                $summary['failures']++;
                Log::warning('Uncertain payment creation recovery failed', [
                    'operation' => 'payment_creation_recovery',
                    'payment_id' => $paymentId,
                    'exception_type' => $exception::class,
                ]);
            }
        }

        $remainingPaymentLimit = max(0, $limit - $summary['payments_checked']);

        $paymentIds = Payment::query()
            ->where('provider', $this->provider->name())
            ->whereNotIn('id', $uncertainIds)
            ->whereNotNull('provider_payment_id')
            ->whereIn('status', [
                PaymentAttemptStatus::Created->value,
                PaymentAttemptStatus::Open->value,
                PaymentAttemptStatus::Pending->value,
                PaymentAttemptStatus::Authorized->value,
                PaymentAttemptStatus::Paid->value,
            ])
            ->whereHas('order', fn ($query) => $query->whereIn('payment_status', [
                PaymentStatus::Unpaid->value,
                PaymentStatus::Pending->value,
                PaymentStatus::Failed->value,
            ]))
            ->orderBy('id')
            ->limit($remainingPaymentLimit)
            ->pluck('id');

        foreach ($paymentIds as $paymentId) {
            $summary['payments_checked']++;
            try {
                $result = $this->payments->sync(Payment::query()->findOrFail($paymentId), 'reconciliation');
                if ($result->eventProcessed) {
                    $summary['payments_changed']++;
                }
            } catch (Throwable $exception) {
                $summary['failures']++;
                Log::warning('Payment reconciliation failed', [
                    'operation' => 'payment_reconciliation',
                    'payment_id' => $paymentId,
                    'exception_type' => $exception::class,
                ]);
            }
        }

        $refundIds = Refund::query()
            ->whereIn('status', [RefundStatus::Requested->value, RefundStatus::Processing->value])
            ->whereHas('payment', fn ($query) => $query->where('provider', $this->provider->name()))
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        foreach ($refundIds as $refundId) {
            $summary['refunds_checked']++;
            try {
                $before = Refund::query()->findOrFail($refundId);
                $after = $before->provider_refund_id
                    ? $this->refunds->sync($before)
                    : $this->refunds->submit($before);
                if ($after->status !== $before->status
                    || $after->provider_status !== $before->provider_status
                    || $after->provider_refund_id !== $before->provider_refund_id) {
                    $summary['refunds_changed']++;
                }
            } catch (Throwable $exception) {
                $summary['failures']++;
                Log::warning('Refund reconciliation failed', [
                    'operation' => 'refund_reconciliation',
                    'refund_id' => $refundId,
                    'exception_type' => $exception::class,
                ]);
            }
        }

        return $summary;
    }
}
