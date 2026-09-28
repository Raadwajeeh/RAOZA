<?php

namespace App\Domain\Returns\Services;

use App\Domain\Commerce\Enums\PaymentStatus;
use App\Domain\Commerce\Models\Order;
use App\Domain\Payments\Contracts\PaymentProvider;
use App\Domain\Payments\Data\ProviderRefund;
use App\Domain\Payments\Data\ProviderRefundRequest;
use App\Domain\Payments\Enums\PaymentAttemptStatus;
use App\Domain\Payments\Exceptions\ProviderOperationException;
use App\Domain\Payments\Models\Payment;
use App\Domain\Returns\Enums\RefundStatus;
use App\Domain\Returns\Enums\ReturnStatus;
use App\Domain\Returns\Models\Refund;
use App\Domain\Returns\Models\ReturnRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class RefundService
{
    public function __construct(private PaymentProvider $provider) {}

    public function request(Order $order, int $amount, ?ReturnRequest $return = null, ?string $reason = null, ?string $idempotencyKey = null): Refund
    {
        if ($amount <= 0) {
            throw new RuntimeException('Refund amount must be positive.');
        }

        return DB::transaction(function () use ($order, $amount, $return, $reason, $idempotencyKey): Refund {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $key = $idempotencyKey ?: Str::uuid()->toString();
            $existing = Refund::query()->where('idempotency_key', $key)->lockForUpdate()->first();

            if ($existing) {
                if ($existing->order_id !== $lockedOrder->id || $existing->amount !== $amount || $existing->return_id !== $return?->id) {
                    throw new RuntimeException('Refund idempotency key was already used for a different request.');
                }

                return $existing;
            }

            if (! in_array($lockedOrder->payment_status, [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded], true)) {
                throw new RuntimeException('Only paid or partially refunded orders can be refunded.');
            }
            if ($return && ($return->order_id !== $lockedOrder->id || ! in_array($return->status, [ReturnStatus::Inspected, ReturnStatus::Completed], true))) {
                throw new RuntimeException('A linked return must belong to this order and be inspected before a refund is requested.');
            }

            $payment = $this->authoritativePayment($lockedOrder, true);
            $committed = (int) Refund::query()
                ->where('payment_id', $payment->id)
                ->whereIn('status', [RefundStatus::Requested->value, RefundStatus::Processing->value, RefundStatus::Succeeded->value])
                ->sum('amount');

            if ($amount > $payment->amount - $committed) {
                throw new RuntimeException('Refund exceeds the remaining refundable amount.');
            }

            return Refund::query()->create([
                'payment_id' => $payment->id,
                'order_id' => $lockedOrder->id,
                'return_id' => $return?->id,
                'amount' => $amount,
                'currency' => $payment->currency,
                'reason' => $reason,
                'status' => RefundStatus::Requested,
                'idempotency_key' => $key,
                'requested_at' => now(),
            ]);
        }, 3);
    }

    public function submit(Refund $refund): Refund
    {
        [$claimed, $ownsSubmission] = DB::transaction(function () use ($refund): array {
            $locked = Refund::query()->whereKey($refund->id)->lockForUpdate()->with('payment.order')->firstOrFail();
            $this->assertProvider($locked->payment);

            if (in_array($locked->status, [RefundStatus::Succeeded, RefundStatus::Failed, RefundStatus::Cancelled], true)) {
                return [$locked, false];
            }
            if ($locked->provider_refund_id) {
                return [$locked, false];
            }

            // Another request or reconciliation worker owns a recent submission.
            // A stale claim is recoverable after the remote-call crash window.
            if ($locked->status === RefundStatus::Processing
                && $locked->submission_started_at?->isAfter(now()->subMinutes(5))) {
                return [$locked, false];
            }

            $metadata = $locked->metadata ?? [];
            $metadata['provider_reference'] ??= $this->providerReference($locked);
            $metadata['submission_attempts'] = ((int) ($metadata['submission_attempts'] ?? 0)) + 1;
            $metadata['last_submission_attempt_at'] = now()->toIso8601String();
            unset($metadata['last_error']);

            $locked->update([
                'status' => RefundStatus::Processing,
                'submission_started_at' => now(),
                'metadata' => $metadata,
            ]);

            return [$locked->refresh()->load('payment.order'), true];
        }, 3);

        if (in_array($claimed->status, [RefundStatus::Succeeded, RefundStatus::Failed, RefundStatus::Cancelled], true)) {
            return $claimed;
        }
        if ($claimed->provider_refund_id) {
            return $this->sync($claimed);
        }
        if (! $ownsSubmission) {
            return $claimed;
        }

        $providerRefundId = null;
        try {
            $reference = (string) $claimed->metadata['provider_reference'];
            $remote = $this->provider->findRefund($claimed->payment->provider_payment_id, $reference);
            if (! $remote) {
                $remote = $this->provider->createRefund(new ProviderRefundRequest(
                    $claimed->payment->provider_payment_id,
                    $claimed->amount,
                    $claimed->currency,
                    $reference,
                    'RAOZA refund '.$claimed->order->order_number,
                    $claimed->id,
                ));
            }
            $providerRefundId = $remote->id;

            return $this->applyProviderState($claimed, $remote);
        } catch (ProviderOperationException $exception) {
            $this->recordSubmissionFailure($claimed, $exception, $providerRefundId);
            throw $exception;
        } catch (Throwable $exception) {
            $safe = new ProviderOperationException('Refund provider outcome is uncertain; reconciliation is required.', true, $exception);
            $this->recordSubmissionFailure($claimed, $safe, $providerRefundId);
            throw $safe;
        }
    }

    public function sync(Refund $refund): Refund
    {
        $current = Refund::query()->with('payment.order')->findOrFail($refund->id);
        $this->assertProvider($current->payment);
        if (! $current->provider_refund_id) {
            throw new RuntimeException('Refund has no provider refund identifier.');
        }

        try {
            $remote = $this->provider->fetchRefund($current->payment->provider_payment_id, $current->provider_refund_id);

            return $this->applyProviderState($current, $remote);
        } catch (Throwable $exception) {
            Log::warning('Refund provider synchronization failed', [
                'refund_id' => $current->id,
                'order_id' => $current->order_id,
                'payment_id' => $current->payment_id,
                'provider_payment_id' => $current->payment->provider_payment_id,
                'provider_refund_id' => $current->provider_refund_id,
                'exception_type' => $exception::class,
            ]);
            throw $exception;
        }
    }

    public function refundableAmount(Order $order): int
    {
        $payment = $this->authoritativePayment($order, false);
        $committed = (int) Refund::query()
            ->where('payment_id', $payment->id)
            ->whereIn('status', [RefundStatus::Requested->value, RefundStatus::Processing->value, RefundStatus::Succeeded->value])
            ->sum('amount');

        return max(0, $payment->amount - $committed);
    }

    private function applyProviderState(Refund $refund, ProviderRefund $remote): Refund
    {
        return DB::transaction(function () use ($refund, $remote): Refund {
            $locked = Refund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
            $payment = Payment::query()->whereKey($locked->payment_id)->lockForUpdate()->firstOrFail();
            $order = Order::query()->whereKey($locked->order_id)->lockForUpdate()->firstOrFail();
            $this->assertProvider($payment);
            $reference = $this->providerReference($locked);

            if ($remote->id === ''
                || $remote->paymentId !== $payment->provider_payment_id
                || $remote->amount !== $locked->amount
                || strtoupper($remote->currency) !== strtoupper($locked->currency)
                || ($remote->reference !== null && $remote->reference !== $reference)
                || ($locked->provider_refund_id !== null && $locked->provider_refund_id !== $remote->id)) {
                throw new RuntimeException('Provider refund does not match the internal refund.');
            }

            $mapped = $this->mapProviderStatus($remote->status);
            if ($locked->status === RefundStatus::Succeeded && $mapped !== RefundStatus::Succeeded) {
                return $locked;
            }
            if (in_array($locked->status, [RefundStatus::Failed, RefundStatus::Cancelled], true) && $mapped !== $locked->status) {
                return $locked;
            }

            $metadata = $locked->metadata ?? [];
            $metadata['provider_reference'] = $reference;
            if ($remote->createdAt !== null) {
                $metadata['provider_created_at'] = $remote->createdAt;
            }

            $terminal = in_array($mapped, [RefundStatus::Succeeded, RefundStatus::Failed, RefundStatus::Cancelled], true);
            $locked->update([
                'provider_refund_id' => $remote->id,
                'provider_status' => strtolower($remote->status),
                'status' => $mapped,
                'last_synced_at' => now(),
                'processed_at' => $terminal ? ($locked->processed_at ?: now()) : null,
                'metadata' => $metadata,
            ]);

            $this->recalculateOrderPaymentStatus($order, $payment);

            return $locked->refresh();
        }, 3);
    }

    private function recalculateOrderPaymentStatus(Order $order, Payment $payment): void
    {
        $succeeded = (int) Refund::query()
            ->where('payment_id', $payment->id)
            ->where('status', RefundStatus::Succeeded->value)
            ->sum('amount');

        if ($succeeded > $payment->amount) {
            throw new RuntimeException('Successful refunds exceed the paid amount.');
        }

        $status = match (true) {
            $succeeded === 0 => PaymentStatus::Paid,
            $succeeded < $payment->amount => PaymentStatus::PartiallyRefunded,
            default => PaymentStatus::Refunded,
        };
        $order->update(['payment_status' => $status]);
    }

    private function authoritativePayment(Order $order, bool $lock): Payment
    {
        $query = Payment::query()
            ->where('order_id', $order->id)
            ->where('status', PaymentAttemptStatus::Paid->value)
            ->whereNotNull('provider_payment_id')
            ->orderBy('id');
        if ($lock) {
            $query->lockForUpdate();
        }
        $payments = $query->get();

        if ($payments->count() !== 1) {
            throw new RuntimeException('Order does not have exactly one authoritative paid provider payment.');
        }

        return $payments->first();
    }

    private function assertProvider(Payment $payment): void
    {
        if ($payment->provider !== $this->provider->name() || ! $payment->provider_payment_id) {
            throw new RuntimeException('Refund payment provider is not available for this operation.');
        }
    }

    private function providerReference(Refund $refund): string
    {
        return hash('sha256', 'raoza-refund|'.$refund->idempotency_key);
    }

    private function mapProviderStatus(string $status): RefundStatus
    {
        return match (strtolower($status)) {
            'queued', 'pending', 'processing' => RefundStatus::Processing,
            'refunded' => RefundStatus::Succeeded,
            'failed' => RefundStatus::Failed,
            'canceled', 'cancelled' => RefundStatus::Cancelled,
            default => throw new RuntimeException('Unsupported provider refund status.'),
        };
    }

    private function recordSubmissionFailure(Refund $refund, ProviderOperationException $exception, ?string $providerRefundId = null): void
    {
        DB::transaction(function () use ($refund, $exception): void {
            $locked = Refund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
            if ($locked->provider_refund_id || $locked->status === RefundStatus::Succeeded) {
                return;
            }
            $metadata = $locked->metadata ?? [];
            $metadata['last_error'] = ['type' => $exception::class, 'at' => now()->toIso8601String(), 'outcome_uncertain' => $exception->outcomeUncertain];
            $locked->update([
                'status' => $exception->outcomeUncertain ? RefundStatus::Processing : RefundStatus::Failed,
                'provider_status' => $exception->outcomeUncertain ? 'unknown' : 'rejected',
                'processed_at' => $exception->outcomeUncertain ? null : now(),
                'metadata' => $metadata,
            ]);
        }, 3);

        Log::warning('Refund provider operation failed', [
            'refund_id' => $refund->id,
            'order_id' => $refund->order_id,
            'payment_id' => $refund->payment_id,
            'provider_payment_id' => $refund->payment->provider_payment_id,
            'provider_refund_id' => $providerRefundId ?? $refund->provider_refund_id,
            'outcome_uncertain' => $exception->outcomeUncertain,
            'exception_type' => $exception::class,
        ]);
    }
}
