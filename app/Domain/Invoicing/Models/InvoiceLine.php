<?php

namespace App\Domain\Invoicing\Models;

use App\Domain\Commerce\Models\OrderItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class InvoiceLine extends Model
{
    protected $fillable = [
        'invoice_id', 'source_invoice_line_id', 'source_order_item_id', 'position', 'line_type', 'description',
        'sku', 'quantity', 'unit_amount', 'subtotal_amount', 'discount_amount', 'net_amount', 'tax_amount',
        'total_amount', 'tax_rate_basis_points', 'options_snapshot', 'product_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer', 'quantity' => 'integer', 'unit_amount' => 'integer',
            'subtotal_amount' => 'integer', 'discount_amount' => 'integer', 'net_amount' => 'integer',
            'tax_amount' => 'integer', 'total_amount' => 'integer', 'tax_rate_basis_points' => 'integer',
            'options_snapshot' => 'array', 'product_snapshot' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Issued accounting document lines are immutable.'));
        static::deleting(fn () => throw new LogicException('Issued accounting document lines cannot be deleted.'));
    }

    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
    public function sourceInvoiceLine(): BelongsTo { return $this->belongsTo(self::class, 'source_invoice_line_id'); }
    public function sourceOrderItem(): BelongsTo { return $this->belongsTo(OrderItem::class, 'source_order_item_id'); }
}
