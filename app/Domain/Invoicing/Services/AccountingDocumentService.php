<?php

namespace App\Domain\Invoicing\Services;

use App\Domain\Commerce\Models\Order;
use App\Domain\Commerce\Services\MoneyService;
use App\Domain\Content\Services\StoreInformation;
use App\Domain\Invoicing\Enums\InvoiceStatus;
use App\Domain\Invoicing\Enums\InvoiceType;
use App\Domain\Invoicing\Models\Invoice;
use App\Domain\Payments\Enums\PaymentAttemptStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Returns\Enums\RefundStatus;
use App\Domain\Returns\Models\Refund;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class AccountingDocumentService
{
    public function __construct(
        private InvoicingReadiness $readiness,
        private InvoiceNumberService $numbers,
        private StoreInformation $storeInformation,
        private MoneyService $money,
        private InvoicePdfService $pdf,
    ) {}

    public function issueInvoiceForPaidOrder(Order $order, Payment $payment): ?Invoice
    {
        if (! $this->readiness->ready()) return null;

        $invoice = DB::transaction(function () use ($order, $payment): Invoice {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->with(['items', 'addresses'])->firstOrFail();
            $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $sourceKey = 'payment:'.$lockedPayment->id;
            $existing = Invoice::query()->where('source_key', $sourceKey)->first();
            if ($existing) return $existing;

            if ($lockedPayment->order_id !== $lockedOrder->id
                || $lockedPayment->status !== PaymentAttemptStatus::Paid
                || $lockedPayment->amount !== $lockedOrder->total_amount
                || strtoupper($lockedPayment->currency) !== strtoupper($lockedOrder->currency)
                || ! $lockedPayment->paid_at) {
                throw new RuntimeException('An official invoice requires one confirmed, matching payment.');
            }

            $issuedAt = $lockedPayment->paid_at;
            $number = $this->numbers->next(InvoiceType::Invoice, $issuedAt);
            $invoice = Invoice::query()->create([
                'order_id' => $lockedOrder->id,
                'payment_id' => $lockedPayment->id,
                'document_type' => InvoiceType::Invoice,
                'document_number' => $number['number'],
                'source_key' => $sourceKey,
                'sequence_value' => $number['value'],
                'sequence_scope' => $number['scope'],
                'status' => InvoiceStatus::Issued,
                'currency' => strtoupper($lockedOrder->currency),
                'seller_snapshot' => $this->sellerSnapshot(),
                'buyer_snapshot' => $this->buyerSnapshot($lockedOrder),
                'payment_snapshot' => ['provider' => $lockedPayment->provider, 'method' => $lockedPayment->method, 'paid_at' => $lockedPayment->paid_at->toIso8601String()],
                'subtotal_amount' => $lockedOrder->subtotal_amount,
                'discount_amount' => $lockedOrder->discount_amount,
                'shipping_amount' => $lockedOrder->shipping_amount,
                'net_amount' => $lockedOrder->total_amount - $lockedOrder->tax_amount,
                'tax_amount' => $lockedOrder->tax_amount,
                'total_amount' => $lockedOrder->total_amount,
                'tax_rate_basis_points' => $lockedOrder->tax_rate_basis_points,
                'issued_at' => $issuedAt,
            ]);

            $position = 1;
            foreach ($lockedOrder->items as $item) {
                $invoice->lines()->create([
                    'source_order_item_id' => $item->id, 'position' => $position++, 'line_type' => 'product',
                    'description' => $item->product_name, 'sku' => $item->sku, 'quantity' => $item->quantity,
                    'unit_amount' => $item->unit_price, 'subtotal_amount' => $item->subtotal_amount,
                    'discount_amount' => $item->discount_amount, 'net_amount' => $item->total_amount - $item->tax_amount,
                    'tax_amount' => $item->tax_amount, 'total_amount' => $item->total_amount,
                    'tax_rate_basis_points' => $lockedOrder->tax_rate_basis_points,
                    'options_snapshot' => $item->variant_options, 'product_snapshot' => $item->product_snapshot,
                ]);
            }
            if ($lockedOrder->shipping_amount > 0) {
                $invoice->lines()->create([
                    'position' => $position, 'line_type' => 'shipping', 'description' => $lockedOrder->shipping_method_name ?: 'Shipping',
                    'quantity' => 1, 'unit_amount' => $lockedOrder->shipping_amount, 'subtotal_amount' => $lockedOrder->shipping_amount,
                    'discount_amount' => 0, 'net_amount' => $lockedOrder->shipping_amount - $lockedOrder->shipping_tax_amount,
                    'tax_amount' => $lockedOrder->shipping_tax_amount, 'total_amount' => $lockedOrder->shipping_amount,
                    'tax_rate_basis_points' => $lockedOrder->tax_rate_basis_points,
                ]);
            }
            $this->assertTotals($invoice->load('lines'));
            return $invoice;
        }, 5);

        $this->pdf->ensureStored($invoice);
        return $invoice->fresh(['lines']);
    }

    public function issueCreditNoteForSucceededRefund(Refund $refund): ?Invoice
    {
        if (! $this->readiness->ready(InvoiceType::CreditNote)) return null;
        $refund->loadMissing(['order', 'payment']);
        $original = Invoice::query()->where('source_key', 'payment:'.$refund->payment_id)->first();
        if (! $original) {
            $original = $this->issueInvoiceForPaidOrder($refund->order, $refund->payment);
        }
        if (! $original) return null;

        $credit = DB::transaction(function () use ($refund, $original): Invoice {
            $lockedRefund = Refund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
            $lockedOriginal = Invoice::query()->whereKey($original->id)->lockForUpdate()->with('lines')->firstOrFail();
            $sourceKey = 'refund:'.$lockedRefund->id;
            $existing = Invoice::query()->where('source_key', $sourceKey)->first();
            if ($existing) return $existing;
            if ($lockedRefund->status !== RefundStatus::Succeeded || ! $lockedRefund->processed_at) {
                throw new RuntimeException('A credit note requires a confirmed provider refund.');
            }
            $credited = (int) Invoice::query()->where('original_invoice_id', $lockedOriginal->id)->sum('total_amount');
            if ($lockedRefund->amount <= 0 || $credited + $lockedRefund->amount > $lockedOriginal->total_amount) {
                throw new RuntimeException('Credit notes cannot exceed the original invoice total.');
            }

            $issuedAt = $lockedRefund->processed_at;
            $number = $this->numbers->next(InvoiceType::CreditNote, $issuedAt);
            $previousTax = (int) Invoice::query()->where('original_invoice_id', $lockedOriginal->id)->sum('tax_amount');
            $targetTax = $credited + $lockedRefund->amount === $lockedOriginal->total_amount
                ? $lockedOriginal->tax_amount - $previousTax
                : min(
                    $lockedOriginal->tax_amount - $previousTax,
                    $this->money->extractIncludedTax($lockedRefund->amount, $lockedOriginal->tax_rate_basis_points),
                );
            $credit = Invoice::query()->create([
                'order_id' => $lockedRefund->order_id, 'payment_id' => $lockedRefund->payment_id,
                'refund_id' => $lockedRefund->id, 'original_invoice_id' => $lockedOriginal->id,
                'document_type' => InvoiceType::CreditNote, 'document_number' => $number['number'],
                'source_key' => $sourceKey, 'sequence_value' => $number['value'], 'sequence_scope' => $number['scope'],
                'status' => InvoiceStatus::Issued, 'currency' => $lockedRefund->currency,
                'seller_snapshot' => $lockedOriginal->seller_snapshot, 'buyer_snapshot' => $lockedOriginal->buyer_snapshot,
                'payment_snapshot' => ['refund_id' => $lockedRefund->id, 'reason' => $lockedRefund->reason, 'refunded_at' => $issuedAt->toIso8601String()],
                'subtotal_amount' => $lockedRefund->amount, 'discount_amount' => 0, 'shipping_amount' => 0,
                'net_amount' => $lockedRefund->amount - $targetTax, 'tax_amount' => $targetTax,
                'total_amount' => $lockedRefund->amount, 'tax_rate_basis_points' => $lockedOriginal->tax_rate_basis_points,
                'issued_at' => $issuedAt,
            ]);

            $previousByLine = DB::table('invoice_lines')->join('invoices', 'invoices.id', '=', 'invoice_lines.invoice_id')
                ->where('invoices.original_invoice_id', $lockedOriginal->id)
                ->whereNotNull('invoice_lines.source_invoice_line_id')
                ->selectRaw('invoice_lines.source_invoice_line_id, SUM(invoice_lines.total_amount) AS credited, SUM(invoice_lines.tax_amount) AS credited_tax')
                ->groupBy('invoice_lines.source_invoice_line_id')->get()->keyBy('source_invoice_line_id');
            $remaining = $lockedOriginal->lines->map(fn ($line) => max(0, $line->total_amount - (int) ($previousByLine->get($line->id)?->credited ?? 0)))->all();
            $remainingTax = $lockedOriginal->lines->map(fn ($line) => max(0, $line->tax_amount - (int) ($previousByLine->get($line->id)?->credited_tax ?? 0)))->all();
            $grossAllocations = $this->money->allocate($lockedRefund->amount, $remaining);
            $taxCaps = array_map(fn (int $tax, int $gross): int => min($tax, $gross), $remainingTax, $grossAllocations);
            $taxAllocations = $this->allocateCapped($targetTax, $grossAllocations, $taxCaps);
            $position = 1;
            foreach ($lockedOriginal->lines as $index => $sourceLine) {
                $gross = $grossAllocations[$index];
                if ($gross === 0) continue;
                $tax = $taxAllocations[$index];
                $credit->lines()->create([
                    'source_invoice_line_id' => $sourceLine->id, 'source_order_item_id' => $sourceLine->source_order_item_id,
                    'position' => $position++, 'line_type' => $sourceLine->line_type,
                    'description' => $sourceLine->description, 'sku' => $sourceLine->sku, 'quantity' => 1,
                    'unit_amount' => $gross, 'subtotal_amount' => $gross, 'discount_amount' => 0,
                    'net_amount' => $gross - $tax, 'tax_amount' => $tax, 'total_amount' => $gross,
                    'tax_rate_basis_points' => $sourceLine->tax_rate_basis_points,
                    'options_snapshot' => $sourceLine->options_snapshot, 'product_snapshot' => $sourceLine->product_snapshot,
                ]);
            }
            $this->assertTotals($credit->load('lines'));
            return $credit;
        }, 5);

        $this->pdf->ensureStored($credit);
        return $credit->fresh(['lines']);
    }

    private function sellerSnapshot(): array
    {
        $store = $this->storeInformation->get();
        return collect($store)->only(['brand_name', 'company_name', 'contact_email', 'contact_phone', 'street', 'house_number', 'addition', 'postal_code', 'city', 'country', 'country_code', 'registration_number', 'vat_number'])->all();
    }

    private function buyerSnapshot(Order $order): array
    {
        $address = $order->addresses->firstWhere('type', 'billing');
        if (! $address) throw new RuntimeException('A billing-address snapshot is required for invoicing.');
        return [...$address->only(['first_name', 'last_name', 'company', 'street', 'house_number', 'addition', 'postal_code', 'city', 'country_code']), 'email' => $order->customer_email, 'phone' => $order->customer_phone];
    }

    private function assertTotals(Invoice $invoice): void
    {
        if ($invoice->lines->sum('net_amount') !== $invoice->net_amount
            || $invoice->lines->sum('tax_amount') !== $invoice->tax_amount
            || $invoice->lines->sum('total_amount') !== $invoice->total_amount
            || $invoice->net_amount + $invoice->tax_amount !== $invoice->total_amount) {
            throw new RuntimeException('Accounting document line totals do not reconcile.');
        }
    }

    /** @param list<int> $weights @param list<int> $caps @return list<int> */
    private function allocateCapped(int $amount, array $weights, array $caps): array
    {
        if ($amount > array_sum($caps)) throw new RuntimeException('The VAT credit exceeds the remaining invoice VAT.');
        $result = array_fill(0, count($weights), 0);
        $remaining = $amount;
        while ($remaining > 0) {
            $activeWeights = [];
            foreach ($weights as $index => $weight) {
                $activeWeights[$index] = $result[$index] < $caps[$index] ? max(1, $weight) : 0;
            }
            $round = $this->money->allocate($remaining, array_values($activeWeights));
            $consumed = 0;
            foreach ($round as $index => $share) {
                $granted = min($share, $caps[$index] - $result[$index]);
                $result[$index] += $granted;
                $consumed += $granted;
            }
            if ($consumed === 0) throw new RuntimeException('The VAT credit could not be allocated safely.');
            $remaining -= $consumed;
        }
        return $result;
    }
}
