<div style="max-width:640px;margin:0 auto;font-family:Arial,sans-serif;color:#111;line-height:1.6">
    @include('emails.partials.logo')
    <h2>Your secure order link.</h2>
    <p>Use the button below to view guest order <strong>{{ $order->order_number }}</strong>.</p>
    <p style="margin:28px 0"><a href="{{ $accessUrl }}" style="display:inline-block;background:#330313;color:#FFF6E6;padding:14px 22px;text-decoration:none">View your order</a></p>
    <p>This private link expires at {{ $expiresAt->format('d M Y H:i T') }}. Do not forward it. If you did not request this email, you can ignore it.</p>
    @include('emails.partials.footer')
</div>
