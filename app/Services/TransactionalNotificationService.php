<?php

namespace App\Services;

use App\Models\NotificationDispatch;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

final class TransactionalNotificationService
{
    public function queueOnce(string $key, string $email, Mailable $mail, ?object $subject = null): bool
    {
        return DB::transaction(function () use ($key, $email, $mail, $subject): bool {
            $inserted = NotificationDispatch::query()->insertOrIgnore([
                'dispatch_key' => hash('sha256', $key),
                'type' => $mail::class,
                'recipient_hash' => hash('sha256', mb_strtolower(trim($email))),
                'subject_type' => $subject ? $subject::class : null,
                'subject_id' => $subject?->getKey(),
                'queued_at' => now(),
            ]);
            if ($inserted !== 1) return false;
            Mail::to($email)->queue($mail);
            return true;
        }, 3);
    }

    public function sendOnce(string $key, string $email, Mailable $mail, ?object $subject = null): bool
    {
        return DB::transaction(function () use ($key, $email, $mail, $subject): bool {
            $inserted = NotificationDispatch::query()->insertOrIgnore([
                'dispatch_key'=>hash('sha256',$key),'type'=>$mail::class,
                'recipient_hash'=>hash('sha256',mb_strtolower(trim($email))),
                'subject_type'=>$subject ? $subject::class : null,'subject_id'=>$subject?->getKey(),'queued_at'=>now(),
            ]);
            if ($inserted !== 1) return false;
            Mail::to($email)->send($mail);
            return true;
        }, 3);
    }
}
