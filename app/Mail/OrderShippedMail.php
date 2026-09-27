<?php
namespace App\Mail; use App\Domain\Commerce\Models\Order; use App\Domain\Fulfillment\Models\Shipment; use Illuminate\Bus\Queueable; use Illuminate\Contracts\Queue\ShouldQueue; use Illuminate\Mail\Mailable; use Illuminate\Queue\SerializesModels;
class OrderShippedMail extends Mailable implements ShouldQueue {use Queueable,SerializesModels;public function __construct(public Order $order,public Shipment $shipment){} public function build(){return $this->subject('Your RAOZA order has shipped — '.$this->order->order_number)->view('emails.order-shipped');}}
