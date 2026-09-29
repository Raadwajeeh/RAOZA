<?php

namespace App\Domain\Content\Services;

use App\Domain\Content\Models\SiteContent;

final class StoreInformation
{
    /** @return array<string, mixed> */
    public function get(): array
    {
        $defaults = config('raoza.store', []);
        $stored = SiteContent::query()->where('key', 'store_information')->value('value') ?? [];

        return array_replace($defaults, $stored);
    }
}
