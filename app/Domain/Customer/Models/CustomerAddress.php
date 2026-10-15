<?php

namespace App\Domain\Customer\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerAddress extends Model
{
    protected $fillable = [
        'user_id', 'label', 'first_name', 'last_name', 'company', 'phone', 'street', 'house_number',
        'addition', 'postal_code', 'city', 'country_code', 'is_shipping', 'is_billing',
        'is_default_shipping', 'is_default_billing',
    ];

    protected function casts(): array
    {
        return [
            'is_shipping' => 'boolean',
            'is_billing' => 'boolean',
            'is_default_shipping' => 'boolean',
            'is_default_billing' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
