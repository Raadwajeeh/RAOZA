<?php

namespace App\Domain\Invoicing\Services;

use App\Domain\Content\Services\StoreInformation;
use App\Domain\Invoicing\Enums\InvoiceType;

final class InvoicingReadiness
{
    public function __construct(private StoreInformation $storeInformation) {}

    /** @return list<string> */
    public function blockers(InvoiceType $type = InvoiceType::Invoice): array
    {
        $blockers = [];
        if (! config('invoicing.issuance_enabled')) $blockers[] = 'Official invoice issuance is disabled.';
        if (! config('invoicing.accounting_policy_approved')) $blockers[] = 'The accounting and VAT policy has not been approved.';
        if (! config('invoicing.seller_profile_approved')) $blockers[] = 'The legal seller profile has not been approved.';
        if (! config('invoicing.numbering.approved')) $blockers[] = 'The document numbering policy has not been approved.';

        $scope = config('invoicing.numbering.scope');
        if (! in_array($scope, ['continuous', 'yearly'], true)) $blockers[] = 'The numbering scope must be explicitly set to continuous or yearly.';

        $prefix = config('invoicing.numbering.'.($type === InvoiceType::Invoice ? 'invoice_prefix' : 'credit_note_prefix'));
        if (! is_string($prefix) || ! preg_match('/^[A-Z0-9][A-Z0-9-]{0,19}$/D', $prefix)) {
            $blockers[] = 'An approved document prefix is missing or invalid.';
        }

        $padding = (int) config('invoicing.numbering.padding', 0);
        if ($padding < 3 || $padding > 12) $blockers[] = 'The approved sequence padding must be between 3 and 12.';

        $seller = $this->storeInformation->get();
        foreach (['company_name', 'street', 'house_number', 'postal_code', 'city', 'country_code', 'registration_number', 'vat_number'] as $field) {
            if (! is_string($seller[$field] ?? null) || trim($seller[$field]) === '') {
                $blockers[] = "Approved seller field is missing: {$field}.";
            }
        }

        if ($type === InvoiceType::CreditNote && config('invoicing.credit_notes.allocation_method') !== 'pro_rata_original') {
            $blockers[] = 'The credit-note VAT allocation method has not been approved.';
        }

        return $blockers;
    }

    public function ready(InvoiceType $type = InvoiceType::Invoice): bool
    {
        return $this->blockers($type) === [];
    }
}
