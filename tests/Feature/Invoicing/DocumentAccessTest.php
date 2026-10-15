<?php

namespace Tests\Feature\Invoicing;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Commerce\Enums\PaymentStatus;
use App\Domain\Commerce\Services\GuestOrderAccessService;
use App\Domain\Content\Models\SiteContent;
use App\Domain\Invoicing\Services\AccountingDocumentService;
use App\Domain\Payments\Enums\PaymentAttemptStatus;
use App\Domain\Payments\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BuildsCommerceFixtures;
use Tests\TestCase;

class DocumentAccessTest extends TestCase
{
    use BuildsCommerceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config()->set('invoicing.issuance_enabled', true);
        config()->set('invoicing.accounting_policy_approved', true);
        config()->set('invoicing.seller_profile_approved', true);
        config()->set('invoicing.numbering', [
            'approved' => true, 'scope' => 'yearly', 'invoice_prefix' => 'INV',
            'credit_note_prefix' => 'CN', 'padding' => 6,
        ]);
        config()->set('invoicing.credit_notes.allocation_method', 'pro_rata_original');
        SiteContent::query()->create(['key' => 'store_information', 'value' => [
            'company_name' => 'Approved Seller', 'street' => 'Legal street', 'house_number' => '1',
            'postal_code' => '1000 AA', 'city' => 'Amsterdam', 'country_code' => 'NL',
            'registration_number' => 'TEST-KVK', 'vat_number' => 'TEST-VAT',
        ]]);
    }

    public function test_customer_can_download_only_documents_for_owned_orders(): void
    {
        $customer = User::factory()->create();
        $other = User::factory()->create();
        [$order, $invoice] = $this->issuedInvoice($customer->id, 'owner@example.com');

        $this->actingAs($customer)->get(route('account.orders.documents.download', [$order->order_number, $invoice->document_number]))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($other)->get(route('account.orders.documents.download', [$order->order_number, $invoice->document_number]))
            ->assertNotFound();
    }

    public function test_guest_bearer_link_is_required_and_scoped_to_its_order(): void
    {
        [$order, $invoice] = $this->issuedInvoice(null, 'guest@example.com');
        [$otherOrder, $otherInvoice] = $this->issuedInvoice(null, 'other@example.com');
        $accessUrl = app(GuestOrderAccessService::class)->issue($order)['url'];
        $token = basename((string) parse_url($accessUrl, PHP_URL_PATH));

        $this->get(route('guest-orders.documents.download', [$token, $invoice->document_number]))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get(route('guest-orders.documents.download', [$token, $otherInvoice->document_number]))->assertNotFound();
        $this->get(route('guest-orders.documents.download', [str_repeat('x', 64), $invoice->document_number]))->assertNotFound();
        $this->assertNotSame($order->id, $otherOrder->id);
    }

    public function test_staff_permissions_protect_accounting_and_operational_printouts(): void
    {
        [$order, $invoice] = $this->issuedInvoice(null, 'guest@example.com');
        $owner = User::factory()->create(['is_admin' => true, 'role' => AdminRole::Owner]);
        $customer = User::factory()->create();

        $this->get(route('admin.orders.documents.accounting', [$order->order_number, $invoice->document_number]))->assertRedirect();
        $this->actingAs($customer)->get(route('admin.orders.documents.accounting', [$order->order_number, $invoice->document_number]))->assertForbidden();
        $this->actingAs($owner)->get(route('admin.orders.documents.accounting', [$order->order_number, $invoice->document_number]))
            ->assertOk()->assertHeader('content-type', 'application/pdf');

        foreach (['packing-slip', 'picking-list', 'order-sheet'] as $type) {
            $response = $this->actingAs($owner)->get(route('admin.orders.documents.operational', [$order->order_number, $type]));
            $response->assertOk()->assertHeader('content-type', 'application/pdf');
            $this->assertStringStartsWith('%PDF-', $response->getContent());
        }
    }

    public function test_operational_templates_omit_unneeded_financial_and_personal_data(): void
    {
        [$order] = $this->issuedInvoice(null, 'private-buyer@example.com');
        $order->load(['items', 'addresses', 'shipments']);
        $picking = view('documents.operational', ['order' => $order, 'type' => 'picking-list'])->render();
        $packing = view('documents.operational', ['order' => $order, 'type' => 'packing-slip'])->render();

        $this->assertStringNotContainsString('private-buyer@example.com', $picking);
        $this->assertStringNotContainsString('Teststraat', $picking);
        $this->assertStringNotContainsString('Payment', $picking);
        $this->assertStringContainsString('Teststraat', $packing);
        $this->assertStringNotContainsString('private-buyer@example.com', $packing);
        $this->assertStringNotContainsString('unit_price', $packing);
    }

    private function issuedInvoice(?int $userId, string $email): array
    {
        $fixture = $this->checkoutOrder([['price' => 1000]], 0, 'EUR', ['email' => $email]);
        $fixture['order']->update(['user_id' => $userId, 'payment_status' => PaymentStatus::Paid, 'paid_at' => now()]);
        $payment = Payment::query()->create([
            'order_id' => $fixture['order']->id, 'provider' => 'test', 'provider_payment_id' => 'pay-'.$fixture['order']->id,
            'currency' => 'EUR', 'amount' => $fixture['order']->total_amount, 'status' => PaymentAttemptStatus::Paid, 'paid_at' => now(),
        ]);
        $order = $fixture['order']->fresh(['items', 'addresses']);
        $invoice = app(AccountingDocumentService::class)->issueInvoiceForPaidOrder($order, $payment);
        return [$order, $invoice];
    }
}
