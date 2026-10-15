<?php

namespace App\Domain\Invoicing\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceNumberSequence extends Model
{
    protected $fillable = ['document_type', 'scope_key', 'next_value'];

    protected function casts(): array
    {
        return ['next_value' => 'integer'];
    }
}
