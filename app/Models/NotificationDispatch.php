<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class NotificationDispatch extends Model
{
    public $timestamps = false;
    protected $fillable = ['dispatch_key','type','recipient_hash','encrypted_recipient','encrypted_mailable','subject_type','subject_id','status','attempts','claimed_at','next_attempt_at','queued_at','sent_at','failed_at','last_error_type'];
    protected $hidden = ['encrypted_recipient','encrypted_mailable'];
    protected function casts(): array { return ['claimed_at'=>'datetime','next_attempt_at'=>'datetime','queued_at'=>'datetime','sent_at'=>'datetime','failed_at'=>'datetime','attempts'=>'integer']; }
}
