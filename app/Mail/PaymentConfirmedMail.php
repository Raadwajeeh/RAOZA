<?php

namespace App\Mail;

use App\Domain\Commerce\Models\Order;

class PaymentConfirmedMail extends TransactionalMail
{
    public function __construct(public Order $order) {}

    public function build(): static
    {
        return $this->subject('Payment confirmed — '.$this->order->order_number)
            ->view('emails.payment-confirmed');
    }
}
