<div style="max-width:640px;margin:0 auto;font-family:Arial,sans-serif;color:#111;line-height:1.6">
    <h1 style="color:#330313;letter-spacing:.12em">RAOZA</h1>
    <h2>Payment confirmed.</h2>
    <p>Payment for order <strong>{{ $order->order_number }}</strong> has been verified.</p>
    <p>Your order can now move into production. We will send shipment details when it leaves RAOZA.</p>
    @include('emails.partials.footer')
</div>
