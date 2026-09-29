<div style="max-width:640px;margin:0 auto;font-family:Arial,sans-serif;color:#111;line-height:1.6">
    <h1 style="color:#330313;letter-spacing:.12em">RAOZA</h1>
    <h2>Your refund is complete.</h2>
    <p>We completed a refund of <strong>{{ $refund->currency }} {{ number_format($refund->amount / 100, 2) }}</strong> for order <strong>{{ $order->order_number }}</strong>.</p>
    <p>The payment provider has confirmed the refund. Your bank may need additional time to display the funds.</p>
    @include('emails.partials.footer')
</div>
