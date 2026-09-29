<?php

namespace App\Mail;

use App\Domain\Commerce\Models\Order;

class OrderReceivedMail extends TransactionalMail
{
    public function __construct(public Order $order) {}

    public function build(): static
    {
        return $this->subject('We received your RAOZA order '.$this->order->order_number)
            ->view('emails.order-received');
    }
}
