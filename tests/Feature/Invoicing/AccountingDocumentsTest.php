<?php

namespace Tests\Feature\Invoicing;

use App\Domain\Commerce\Enums\PaymentStatus;
use App\Domain\Content\Models\SiteContent;
use App\Domain\Invoicing\Enums\InvoiceType;
use App\Domain\Invoicing\Models\Invoice;
use App\Domain\Invoicing\Services\AccountingDocumentService;
use App\Domain\Payments\Enums\PaymentAttemptStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Contracts\PaymentProvider;
use App\Domain\Payments\Services\PaymentService;
use App\Domain\Payments\Services\PaymentSyncCoordinator;
use App\Domain\Returns\Enums\RefundStatus;
use App\Domain\Returns\Models\Refund;
use App\Domain\Returns\Services\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;
use Tests\Support\BuildsCommerceFixtures;
use Tests\Support\FakePaymentProvider;
use Tests\TestCase;

class AccountingDocumentsTest extends TestCase
{
    use BuildsCommerceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_official_issuance_is_fail_closed_until_policies_and_seller_are_approved(): void
    {
        [$order, $payment] = $this->paidOrder();
        $this->assertNull(app(AccountingDocumentService::class)->issueInvoiceForPaidOrder($order, $payment));
        $this->assertDatabaseCount('invoices', 0);

        $this->approveInvoicing();
        $payment->update(['status' => PaymentAttemptStatus::Open, 'paid_at' => null]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('confirmed');
        app(AccountingDocumentService::class)->issueInvoiceForPaidOrder($order, $payment->fresh());
    }

    public function test_invoice_numbering_snapshots_totals_pdf_and_idempotency_are_consistent(): void
    {
        $this->approveInvoicing();
        [$firstOrder, $firstPayment] = $this->paidOrder([['price' => 1234, 'quantity' => 2], ['price' => 501]], 499);
        [$secondOrder, $secondPayment] = $this->paidOrder([['price' => 1000]], 0, ['email' => 'second@example.com']);
        $service = app(AccountingDocumentService::class);

        $first = $service->issueInvoiceForPaidOrder($firstOrder, $firstPayment);
        $duplicate = $service->issueInvoiceForPaidOrder($firstOrder->fresh(), $firstPayment->fresh());
        $second = $service->issueInvoiceForPaidOrder($secondOrder, $secondPayment);

        $this->assertSame($first->id, $duplicate->id);
        $this->assertSame('INV-'.$first->issued_at->format('Y').'-000001', $first->document_number);
        $this->assertSame('INV-'.$second->issued_at->format('Y').'-000002', $second->document_number);
        $this->assertSame($first->total_amount, $first->lines->sum('total_amount'));
        $this->assertSame($first->tax_amount, $first->lines->sum('tax_amount'));
        $this->assertSame($first->total_amount, $first->net_amount + $first->tax_amount);
        $this->assertSame('Approved RAOZA B.V.', $first->seller_snapshot['company_name']);
        $this->assertSame('Teststraat', $first->buyer_snapshot['street']);
        $this->assertNotNull($first->pdf_path);
        Storage::disk('local')->assertExists($first->pdf_path);
        $contents = Storage::disk('local')->get($first->pdf_path);
        $this->assertStringStartsWith('%PDF-', $contents);
        $this->assertSame(hash('sha256', $contents), $first->pdf_sha256);
        $this->assertDatabaseCount('invoices', 2);
    }

    public function test_issued_snapshots_and_lines_are_immutable_when_source_data_changes(): void
    {
        $this->approveInvoicing();
        [$order, $payment] = $this->paidOrder();
        $invoice = app(AccountingDocumentService::class)->issueInvoiceForPaidOrder($order, $payment);
        $originalBuyer = $invoice->buyer_snapshot;
        $originalProduct = $invoice->lines->first()->product_snapshot;

        $order->addresses()->where('type', 'billing')->update(['street' => 'Changed later']);
        $order->items()->first()->update(['product_name' => 'Changed later']);
        SiteContent::query()->where('key', 'store_information')->update(['value' => ['company_name' => 'Changed later']]);
        $this->assertSame($originalBuyer, $invoice->fresh()->buyer_snapshot);
        $this->assertSame($originalProduct, $invoice->fresh('lines')->lines->first()->product_snapshot);

        $this->expectException(LogicException::class);
        $invoice->update(['total_amount' => $invoice->total_amount + 1]);
    }

    public function test_partial_and_full_credit_notes_reconcile_and_are_idempotent(): void
    {
        $this->approveInvoicing();
        [$order, $payment] = $this->paidOrder([['price' => 1000]], 0);
        $service = app(AccountingDocumentService::class);
        $invoice = $service->issueInvoiceForPaidOrder($order, $payment);
        $firstRefund = $this->succeededRefund($order->id, $payment->id, 333, 'refund-one');
        $secondRefund = $this->succeededRefund($order->id, $payment->id, 667, 'refund-two');

        $first = $service->issueCreditNoteForSucceededRefund($firstRefund);
        $duplicate = $service->issueCreditNoteForSucceededRefund($firstRefund->fresh());
        $second = $service->issueCreditNoteForSucceededRefund($secondRefund);

        $this->assertSame($first->id, $duplicate->id);
        $this->assertSame(InvoiceType::CreditNote, $first->document_type);
        $this->assertSame($invoice->id, $first->original_invoice_id);
        $this->assertSame(1000, $first->total_amount + $second->total_amount);
        $this->assertSame($invoice->tax_amount, $first->tax_amount + $second->tax_amount);
        $this->assertSame(1000, Invoice::query()->where('original_invoice_id', $invoice->id)->sum('total_amount'));
        $this->assertDatabaseCount('invoices', 3);
    }

    public function test_credit_note_requires_confirmed_refund_and_cannot_exceed_original_invoice(): void
    {
        $this->approveInvoicing();
        [$order, $payment] = $this->paidOrder([['price' => 1000]]);
        $service = app(AccountingDocumentService::class);
        $service->issueInvoiceForPaidOrder($order, $payment);
        $pending = Refund::query()->create([
            'payment_id' => $payment->id, 'order_id' => $order->id, 'amount' => 100,
            'currency' => 'EUR', 'status' => RefundStatus::Requested, 'idempotency_key' => 'pending', 'requested_at' => now(),
        ]);
        try {
            $service->issueCreditNoteForSucceededRefund($pending);
            $this->fail('An unconfirmed refund created a credit note.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('confirmed provider refund', $exception->getMessage());
        }

        $oversized = $this->succeededRefund($order->id, $payment->id, 1001, 'oversized');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('cannot exceed');
        $service->issueCreditNoteForSucceededRefund($oversized);
    }

    public function test_confirmed_payment_and_refund_lifecycles_issue_documents_once(): void
    {
        $this->approveInvoicing();
        $provider = new FakePaymentProvider;
        $this->app->instance(PaymentProvider::class, $provider);
        $fixture = $this->checkoutOrder([['price' => 1000]]);
        $payment = app(PaymentService::class)->createAttempt($fixture['order'], 'https://store.test/return', 'https://store.test/webhook');
        $provider->status = 'paid';

        app(PaymentSyncCoordinator::class)->sync($payment, 'webhook');
        app(PaymentSyncCoordinator::class)->sync($payment->fresh(), 'customer_return');
        $this->assertSame(1, Invoice::query()->where('document_type', InvoiceType::Invoice->value)->count());

        $provider->refundStatus = 'refunded';
        $provider->refundId = 're-accounting';
        $refund = app(RefundService::class)->request($fixture['order']->fresh(), 400, idempotencyKey: 'accounting-refund');
        app(RefundService::class)->submit($refund);
        app(RefundService::class)->sync($refund->fresh());

        $this->assertSame(1, Invoice::query()->where('document_type', InvoiceType::CreditNote->value)->count());
        $this->assertSame($refund->id, Invoice::query()->where('document_type', InvoiceType::CreditNote->value)->firstOrFail()->refund_id);
    }

    public function test_many_partial_credits_preserve_exact_final_vat_rounding(): void
    {
        $this->approveInvoicing();
        [$order, $payment] = $this->paidOrder([['price' => 333], ['price' => 777]], 101);
        $service = app(AccountingDocumentService::class);
        $invoice = $service->issueInvoiceForPaidOrder($order, $payment);
        $parts = [1, 2, 3, 205, $invoice->total_amount - 211];
        foreach ($parts as $index => $amount) {
            $service->issueCreditNoteForSucceededRefund($this->succeededRefund($order->id, $payment->id, $amount, 'rounding-'.$index));
        }

        $credits = Invoice::query()->where('original_invoice_id', $invoice->id)->get();
        $this->assertSame($invoice->total_amount, $credits->sum('total_amount'));
        $this->assertSame($invoice->tax_amount, $credits->sum('tax_amount'));
        $this->assertTrue($credits->every(fn (Invoice $credit) => $credit->lines()->sum('total_amount') === $credit->total_amount));
        $this->assertSame(0, DB::table('invoice_lines')->whereColumn('tax_amount', '>', 'total_amount')->count());
    }

    /** @return array{0:\App\Domain\Commerce\Models\Order,1:Payment} */
    private function paidOrder(array $lines = [['price' => 1000]], int $shipping = 0, array $overrides = []): array
    {
        $fixture = $this->checkoutOrder($lines, $shipping, 'EUR', $overrides);
        $paidAt = now();
        $fixture['order']->update(['payment_status' => PaymentStatus::Paid, 'paid_at' => $paidAt]);
        $payment = Payment::query()->create([
            'order_id' => $fixture['order']->id, 'provider' => 'test', 'provider_payment_id' => 'pay-'.$fixture['order']->id,
            'method' => 'ideal', 'currency' => 'EUR', 'amount' => $fixture['order']->total_amount,
            'status' => PaymentAttemptStatus::Paid, 'paid_at' => $paidAt,
        ]);
        return [$fixture['order']->fresh(['items', 'addresses']), $payment];
    }

    private function succeededRefund(int $orderId, int $paymentId, int $amount, string $key): Refund
    {
        return Refund::query()->create([
            'payment_id' => $paymentId, 'order_id' => $orderId, 'provider_refund_id' => 'provider-'.$key,
            'provider_status' => 'refunded', 'amount' => $amount, 'currency' => 'EUR', 'status' => RefundStatus::Succeeded,
            'idempotency_key' => $key, 'requested_at' => now(), 'processed_at' => now(),
        ]);
    }

    private function approveInvoicing(): void
    {
        config()->set('invoicing.issuance_enabled', true);
        config()->set('invoicing.accounting_policy_approved', true);
        config()->set('invoicing.seller_profile_approved', true);
        config()->set('invoicing.numbering.approved', true);
        config()->set('invoicing.numbering.scope', 'yearly');
        config()->set('invoicing.numbering.invoice_prefix', 'INV');
        config()->set('invoicing.numbering.credit_note_prefix', 'CN');
        config()->set('invoicing.numbering.padding', 6);
        config()->set('invoicing.credit_notes.allocation_method', 'pro_rata_original');
        SiteContent::query()->updateOrCreate(['key' => 'store_information'], ['value' => [
            'brand_name' => 'RAOZA', 'company_name' => 'Approved RAOZA B.V.', 'contact_email' => 'accounts@example.test',
            'contact_phone' => null, 'street' => 'Approvedstraat', 'house_number' => '1', 'addition' => null,
            'postal_code' => '1000 AA', 'city' => 'Amsterdam', 'country' => 'Netherlands', 'country_code' => 'NL',
            'registration_number' => 'APPROVED-TEST-KVK', 'vat_number' => 'APPROVED-TEST-VAT',
        ]]);
    }
}
