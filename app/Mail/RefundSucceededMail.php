<?php

namespace App\Mail;

use App\Domain\Commerce\Models\Order;
use App\Domain\Returns\Models\Refund;

class RefundSucceededMail extends TransactionalMail
{
    public function __construct(public Order $order, public Refund $refund) {}

    public function build(): static
    {
        return $this->subject('Refund completed — '.$this->order->order_number)
            ->view('emails.refund-succeeded')
            ->with('store', $this->storeInformation());
    }
}
