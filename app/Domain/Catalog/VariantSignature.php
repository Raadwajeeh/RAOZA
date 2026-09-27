<?php
namespace App\Domain\Catalog;
use InvalidArgumentException;
final class VariantSignature
{
    /** @param array<int> $optionValueIds */
    public static function fromOptionValueIds(array $optionValueIds): string
    {
        $ids = array_values(array_unique(array_map('intval', $optionValueIds)));
        if ($ids === [] || in_array(0, $ids, true)) { throw new InvalidArgumentException('Variant option values must contain positive IDs.'); }
        sort($ids, SORT_NUMERIC);
        return implode('-', $ids);
    }
}
