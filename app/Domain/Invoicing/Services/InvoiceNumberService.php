<?php

namespace App\Domain\Invoicing\Services;

use App\Domain\Invoicing\Enums\InvoiceType;
use App\Domain\Invoicing\Models\InvoiceNumberSequence;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class InvoiceNumberService
{
    /** @return array{number:string,value:int,scope:string} */
    public function next(InvoiceType $type, CarbonInterface $issuedAt): array
    {
        $scopePolicy = config('invoicing.numbering.scope');
        $scope = $scopePolicy === 'yearly' ? $issuedAt->format('Y') : 'continuous';
        $prefix = (string) config('invoicing.numbering.'.($type === InvoiceType::Invoice ? 'invoice_prefix' : 'credit_note_prefix'));
        $padding = (int) config('invoicing.numbering.padding');
        if ($prefix === '' || ! in_array($scopePolicy, ['continuous', 'yearly'], true)) {
            throw new RuntimeException('Invoice numbering policy is not configured.');
        }

        DB::table('invoice_number_sequences')->insertOrIgnore([
            'document_type' => $type->value,
            'scope_key' => $scope,
            'next_value' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $sequence = InvoiceNumberSequence::query()
            ->where('document_type', $type->value)
            ->where('scope_key', $scope)
            ->lockForUpdate()
            ->firstOrFail();
        $value = $sequence->next_value;
        $sequence->update(['next_value' => $value + 1]);
        $parts = [$prefix];
        if ($scopePolicy === 'yearly') $parts[] = $scope;
        $parts[] = str_pad((string) $value, $padding, '0', STR_PAD_LEFT);

        return ['number' => implode('-', $parts), 'value' => $value, 'scope' => $scope];
    }
}
