<?php

namespace App\Domain\Admin\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AuditLog extends Model
{
    public $timestamps = false;
    protected $fillable = ['actor_id','action','subject_type','subject_id','before','after','ip_address','created_at'];
    protected function casts():array{return ['before'=>'array','after'=>'array','created_at'=>'datetime'];}
    protected static function booted():void
    {
        static::updating(fn()=>throw new LogicException('Audit logs are append-only.'));
        static::deleting(fn()=>throw new LogicException('Audit logs are append-only.'));
    }
    public function actor():BelongsTo{return $this->belongsTo(User::class,'actor_id');}
}
