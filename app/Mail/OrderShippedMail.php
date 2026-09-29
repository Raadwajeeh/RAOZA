<?php

namespace App\Mail;

use App\Domain\Commerce\Models\Order;
use App\Domain\Fulfillment\Models\Shipment;

class OrderShippedMail extends TransactionalMail
{
    public function __construct(public Order $order, public Shipment $shipment) {}

    public function build(): static
    {
        return $this->subject('Your RAOZA order has shipped — '.$this->order->order_number)
            ->view('emails.order-shipped');
    }
}
