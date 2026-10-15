<?php

namespace App\Services;

use App\Jobs\DeliverTransactionalNotification;
use App\Models\NotificationDispatch;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Throwable;

final class TransactionalNotificationService
{
    public function queueOnce(string $key, string $email, Mailable $mail, ?object $subject = null): bool
    {
        $dispatch=NotificationDispatch::query()->firstOrCreate(['dispatch_key'=>hash('sha256',$key)], [
            'type'=>$mail::class,'recipient_hash'=>hash('sha256',mb_strtolower(trim($email))),
            'encrypted_recipient'=>Crypt::encryptString($email),
            'encrypted_mailable'=>Crypt::encryptString(serialize($mail)),
            'subject_type'=>$subject ? $subject::class : null,'subject_id'=>$subject?->getKey(),
            'status'=>'pending','attempts'=>0,'next_attempt_at'=>now(),'queued_at'=>now(),
        ]);
        if (! $dispatch->wasRecentlyCreated) return false;
        try { DeliverTransactionalNotification::dispatch($dispatch->id)->afterCommit(); }
        catch (Throwable $e) { Log::error('Notification outbox enqueue failed; recovery remains pending',['dispatch_id'=>$dispatch->id,'exception_type'=>$e::class]); }
        return true;
    }

    public function sendOnce(string $key, string $email, Mailable $mail, ?object $subject = null): bool
    {
        return $this->queueOnce($key,$email,$mail,$subject);
    }
}
