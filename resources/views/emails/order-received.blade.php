<div style="max-width:640px;margin:0 auto;font-family:Arial,sans-serif;color:#111;line-height:1.6">
    @include('emails.partials.logo')
    <h2>We received your order.</h2>
    <p>Thank you for ordering from RAOZA. Your order reference is <strong>{{ $order->order_number }}</strong>.</p>
    <p>Order total: <strong>{{ $order->currency }} {{ number_format($order->total_amount / 100, 2) }}</strong>, including VAT. Production begins only after payment is verified.</p>
    <p>Keep this reference when contacting customer service. We will send another message when payment is confirmed.</p>
    @include('emails.partials.footer')
</div>
