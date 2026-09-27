<?php
namespace App\Domain\Commerce\Models;
use App\Domain\Commerce\Enums\FulfillmentStatus;
use App\Domain\Commerce\Enums\OrderStatus;
use App\Domain\Commerce\Enums\PaymentStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Domain\Payments\Models\Payment;
use App\Domain\Fulfillment\Models\Shipment;
use App\Domain\Returns\Models\ReturnRequest;
use App\Domain\Returns\Models\Refund;
class Order extends Model
{
    protected $fillable=['order_number','user_id','source_cart_id','shipping_method_id','shipping_method_name','customer_email','customer_phone','analytics_consent','marketing_consent','currency','discount_code','subtotal_amount','discount_amount','shipping_amount','tax_amount','tax_rate_basis_points','total_amount','order_status','payment_status','fulfillment_status','placed_at','paid_at','cancelled_at'];
    protected function casts(): array { return ['analytics_consent'=>'boolean','marketing_consent'=>'boolean','subtotal_amount'=>'integer','discount_amount'=>'integer','shipping_amount'=>'integer','tax_amount'=>'integer','tax_rate_basis_points'=>'integer','total_amount'=>'integer','order_status'=>OrderStatus::class,'payment_status'=>PaymentStatus::class,'fulfillment_status'=>FulfillmentStatus::class,'placed_at'=>'datetime','paid_at'=>'datetime','cancelled_at'=>'datetime']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function sourceCart(): BelongsTo { return $this->belongsTo(Cart::class,'source_cart_id'); }
    public function items(): HasMany { return $this->hasMany(OrderItem::class); }
    public function addresses(): HasMany { return $this->hasMany(OrderAddress::class); }
    public function statusHistory(): HasMany { return $this->hasMany(OrderStatusHistory::class); }
    public function payments(): HasMany { return $this->hasMany(Payment::class); }
    public function shipments(): HasMany { return $this->hasMany(Shipment::class); }
    public function returns(): HasMany { return $this->hasMany(ReturnRequest::class); }
    public function refunds(): HasMany { return $this->hasMany(Refund::class); }
}
