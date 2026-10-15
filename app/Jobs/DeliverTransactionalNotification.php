<?php

namespace App\Jobs;

use App\Models\NotificationDispatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

final class DeliverTransactionalNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries=5;
    public int $timeout=60;
    public function __construct(public int $dispatchId) {}
    public function backoff(): array { return [30,120,600,1800]; }

    public function handle(): void
    {
        $dispatch=DB::transaction(function () {
            $row=NotificationDispatch::query()->whereKey($this->dispatchId)->lockForUpdate()->first();
            if (! $row || $row->status === 'sent') return null;
            if ($row->status === 'processing' && $row->claimed_at?->isAfter(now()->subMinutes(10))) return null;
            if (! $row->encrypted_recipient || ! $row->encrypted_mailable) throw new RuntimeException('Legacy notification dispatch has no recoverable payload.');
            $row->update(['status'=>'processing','claimed_at'=>now(),'attempts'=>$row->attempts+1,'last_error_type'=>null]);
            return $row;
        },3);
        if (! $dispatch) return;
        try {
            $recipient=Crypt::decryptString($dispatch->encrypted_recipient);
            $mail=unserialize(Crypt::decryptString($dispatch->encrypted_mailable),['allowed_classes'=>true]);
            if (! $mail instanceof \Illuminate\Mail\Mailable) throw new RuntimeException('Outbox payload is not a mailable.');
            Mail::to($recipient)->sendNow($mail);
            $dispatch->update(['status'=>'sent','sent_at'=>now(),'claimed_at'=>null,'next_attempt_at'=>null,'failed_at'=>null,'last_error_type'=>null]);
        } catch (Throwable $e) {
            $dispatch->update(['status'=>'pending','claimed_at'=>null,'next_attempt_at'=>now()->addMinutes(min(60,2 ** min(5,$dispatch->attempts))),'failed_at'=>now(),'last_error_type'=>$e::class]);
            throw $e;
        }
    }
}
