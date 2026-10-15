<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class NotificationDispatch extends Model
{
    public $timestamps = false;
    protected $fillable = ['dispatch_key', 'type', 'recipient_hash', 'subject_type', 'subject_id', 'queued_at'];
    protected function casts(): array { return ['queued_at' => 'datetime']; }
}
