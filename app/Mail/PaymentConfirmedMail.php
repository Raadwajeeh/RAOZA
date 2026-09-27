<?php
namespace App\Mail; use App\Domain\Commerce\Models\Order; use Illuminate\Bus\Queueable; use Illuminate\Contracts\Queue\ShouldQueue; use Illuminate\Mail\Mailable; use Illuminate\Queue\SerializesModels;
class PaymentConfirmedMail extends Mailable implements ShouldQueue {use Queueable,SerializesModels;public function __construct(public Order $order){} public function build(){return $this->subject('Payment confirmed — '.$this->order->order_number)->view('emails.payment-confirmed');}}
