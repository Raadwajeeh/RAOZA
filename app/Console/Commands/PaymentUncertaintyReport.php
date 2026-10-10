<?php

namespace App\Console\Commands;

use App\Domain\Payments\Enums\PaymentAttemptStatus;
use App\Domain\Payments\Models\Payment;
use Illuminate\Console\Command;

class PaymentUncertaintyReport extends Command
{
    protected $signature = 'commerce:payment-uncertainty-report {--limit=100 : Maximum attempts to display}';

    protected $description = 'List payment attempts that remain uncertain or require provider review';

    public function handle(): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);
        if ($limit === false || $limit < 1 || $limit > 500) {
            $this->error('The --limit value must be an integer between 1 and 500.');

            return self::INVALID;
        }

        $payments = Payment::query()
            ->with('order:id,order_number')
            ->where(function ($query): void {
                $query->whereIn('status', [
                    PaymentAttemptStatus::Creating->value,
                    PaymentAttemptStatus::Created->value,
                    PaymentAttemptStatus::ReviewRequired->value,
                ]);
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $this->table(
            ['Payment', 'Order', 'Status', 'First provider attempt', 'Review reason'],
            $payments->map(fn (Payment $payment): array => [
                $payment->id,
                $payment->order?->order_number,
                $payment->status->value,
                data_get($payment->metadata, 'first_provider_attempt_at'),
                data_get($payment->metadata, 'review_reason'),
            ])->all(),
        );

        if ($payments->contains(fn (Payment $payment): bool => $payment->status === PaymentAttemptStatus::ReviewRequired)) {
            $this->warn('Review-required attempts must be resolved against Mollie before inventory is released or another payment is created.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
