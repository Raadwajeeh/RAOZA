<?php
namespace App\Mail; use App\Domain\Commerce\Models\Order; use Illuminate\Bus\Queueable; use Illuminate\Contracts\Queue\ShouldQueue; use Illuminate\Mail\Mailable; use Illuminate\Queue\SerializesModels;
class OrderReceivedMail extends Mailable implements ShouldQueue {use Queueable,SerializesModels;public function __construct(public Order $order){} public function build(){return $this->subject('We received your RAOZA order '.$this->order->order_number)->view('emails.order-received');}}
