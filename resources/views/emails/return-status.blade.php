<div style="max-width:640px;margin:0 auto;font-family:Arial,sans-serif;color:#111;line-height:1.6">
 @include('emails.partials.logo')
 <h2>Your return status changed.</h2>
 <p>Return <strong>{{ $return->return_number }}</strong> for order <strong>{{ $return->order->order_number }}</strong> is now <strong>{{ str_replace('_', ' ', $return->status->value) }}</strong>.</p>
 <p>This status message is not confirmation of a refund. A confirmed refund is communicated separately.</p>
 @include('emails.partials.footer')
</div>
