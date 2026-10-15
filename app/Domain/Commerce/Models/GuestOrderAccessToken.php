<?php

namespace App\Domain\Commerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuestOrderAccessToken extends Model
{
    protected $fillable = ['order_id', 'token_hash', 'expires_at', 'last_accessed_at', 'revoked_at'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'last_accessed_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
