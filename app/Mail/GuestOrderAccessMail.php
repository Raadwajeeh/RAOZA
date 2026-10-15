<?php

namespace App\Mail;

use App\Domain\Commerce\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class GuestOrderAccessMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public string $accessUrl,
        public \DateTimeInterface $expiresAt,
    ) {}

    public function build(): static
    {
        return $this->subject('Access your RAOZA order '.$this->order->order_number)
            ->view('emails.guest-order-access')
            ->with('store', app(\App\Domain\Content\Services\StoreInformation::class)->get());
    }
}
