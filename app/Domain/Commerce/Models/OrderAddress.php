<?php
namespace App\Domain\Commerce\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class OrderAddress extends Model
{
    protected $fillable=['order_id','type','first_name','last_name','company','street','house_number','addition','postal_code','city','country_code'];
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
}
