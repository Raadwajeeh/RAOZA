<?php

namespace App\Mail;

use App\Domain\Content\Services\StoreInformation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

abstract class TransactionalMail extends Mailable implements ShouldQueueAfterCommit
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 120];
    }

    /** @return array<string, mixed> */
    protected function storeInformation(): array
    {
        return app(StoreInformation::class)->get();
    }
}
