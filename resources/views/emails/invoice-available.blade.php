<div style="max-width:640px;margin:0 auto;font-family:Arial,sans-serif;color:#111;line-height:1.6">
    @include('emails.partials.logo')
    <h2>Your RAOZA document is ready.</h2>
    <p>Document <strong>{{ $invoice->document_number }}</strong> for order {{ $invoice->order->order_number }} is attached.</p>
    <p>This message template is prepared for the approved transactional-mail workflow and is not sent automatically.</p>
    @include('emails.partials.footer')
</div>
