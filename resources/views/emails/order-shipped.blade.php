<div style="max-width:640px;margin:0 auto;font-family:Arial,sans-serif;color:#111;line-height:1.6">
    <h1 style="color:#330313;letter-spacing:.12em">RAOZA</h1>
    <h2>Your order has shipped.</h2>
    <p>Order <strong>{{ $order->order_number }}</strong> is on its way.</p>
    @if($shipment->tracking_number)<p>Tracking reference: <strong>{{ $shipment->tracking_number }}</strong></p>@endif
    @if($shipment->tracking_url)<p><a href="{{ $shipment->tracking_url }}" style="color:#330313;font-weight:bold">Track your shipment</a></p>@endif
    <p>If the delivery address or tracking information looks wrong, contact customer service as soon as possible.</p>
    @include('emails.partials.footer')
</div>
