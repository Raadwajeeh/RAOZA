<?php

namespace App\Domain\Invoicing\Models;

use App\Domain\Commerce\Models\Order;
use App\Domain\Invoicing\Enums\InvoiceStatus;
use App\Domain\Invoicing\Enums\InvoiceType;
use App\Domain\Payments\Models\Payment;
use App\Domain\Returns\Models\Refund;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class Invoice extends Model
{
    protected $fillable = [
        'order_id', 'payment_id', 'refund_id', 'original_invoice_id', 'document_type', 'document_number',
        'source_key', 'sequence_value', 'sequence_scope', 'status', 'currency', 'seller_snapshot',
        'buyer_snapshot', 'payment_snapshot', 'subtotal_amount', 'discount_amount', 'shipping_amount',
        'net_amount', 'tax_amount', 'total_amount', 'tax_rate_basis_points', 'issued_at', 'pdf_path',
        'pdf_sha256', 'pdf_generated_at',
    ];

    protected function casts(): array
    {
        return [
            'document_type' => InvoiceType::class, 'status' => InvoiceStatus::class,
            'seller_snapshot' => 'array', 'buyer_snapshot' => 'array', 'payment_snapshot' => 'array',
            'sequence_value' => 'integer', 'subtotal_amount' => 'integer', 'discount_amount' => 'integer',
            'shipping_amount' => 'integer', 'net_amount' => 'integer', 'tax_amount' => 'integer',
            'total_amount' => 'integer', 'tax_rate_basis_points' => 'integer',
            'issued_at' => 'datetime', 'pdf_generated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (Invoice $invoice): void {
            $allowed = ['pdf_path', 'pdf_sha256', 'pdf_generated_at', 'updated_at'];
            if (array_diff(array_keys($invoice->getDirty()), $allowed) !== []) {
                throw new LogicException('Issued accounting documents are immutable.');
            }
        });
        static::deleting(fn () => throw new LogicException('Issued accounting documents cannot be deleted.'));
    }

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function payment(): BelongsTo { return $this->belongsTo(Payment::class); }
    public function refund(): BelongsTo { return $this->belongsTo(Refund::class); }
    public function originalInvoice(): BelongsTo { return $this->belongsTo(self::class, 'original_invoice_id'); }
    public function lines(): HasMany { return $this->hasMany(InvoiceLine::class)->orderBy('position'); }
}
