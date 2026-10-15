<div style="max-width:640px;margin:0 auto;font-family:Arial,sans-serif;color:#111;line-height:1.6">
 @include('emails.partials.logo')
 <h2>We received your return request.</h2>
 <p>Reference <strong>{{ $return->return_number }}</strong> for order <strong>{{ $return->order->order_number }}</strong> is waiting for review.</p>
 <p>No refund has been issued. We will notify you when its review status changes.</p>
 @include('emails.partials.footer')
</div>
