<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 34px 42px 48px; }
        * { box-sizing: border-box; }
        body { font-family: "DejaVu Sans", sans-serif; color: #241219; font-size: 10px; line-height: 1.45; }
        .top { border-bottom: 3px solid #330313; padding-bottom: 18px; margin-bottom: 24px; }
        .brand { font-family: serif; font-size: 28px; letter-spacing: 5px; color: #330313; }
        .kind { float: right; font-size: 18px; font-weight: bold; text-transform: uppercase; color: #602032; }
        .columns { width: 100%; margin-bottom: 24px; }
        .columns td { width: 50%; vertical-align: top; padding: 0 18px 0 0; }
        .label { color: #7b6670; font-size: 8px; text-transform: uppercase; letter-spacing: 1px; }
        .meta { margin-top: 10px; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 16px; }
        .lines th { background: #330313; color: #fff6e6; text-align: left; padding: 8px 6px; font-size: 8px; text-transform: uppercase; }
        .lines td { border-bottom: 1px solid #eadfe3; padding: 9px 6px; vertical-align: top; }
        .num { text-align: right; white-space: nowrap; }
        .options { color: #78646d; font-size: 8px; }
        .totals { width: 44%; margin: 18px 0 0 auto; border-collapse: collapse; }
        .totals td { padding: 5px 0 5px 12px; }
        .totals .grand td { border-top: 2px solid #330313; font-size: 12px; font-weight: bold; padding-top: 9px; }
        .notice { background: #fff6e6; border-left: 3px solid #d1af6f; padding: 10px; margin-top: 24px; }
        .footer { position: fixed; bottom: -30px; left: 0; right: 0; color: #8a747d; font-size: 8px; text-align: center; }
        .qa { border: 2px solid #9b1c31; color: #9b1c31; padding: 8px; text-align: center; font-weight: bold; letter-spacing: 1px; margin-bottom: 18px; }
    </style>
</head>
<body>
<div class="footer">RAOZA - {{ $invoice->document_number }} - This document is generated from an immutable accounting snapshot.</div>
@if($qaWatermark)<div class="qa">TEST DOCUMENT - NOT FOR ACCOUNTING</div>@endif
<div class="top">
    <span class="kind">{{ $invoice->document_type->value === 'credit_note' ? 'Credit note' : 'Invoice' }}</span>
    <div class="brand">RAOZA</div>
</div>
<table class="columns">
    <tr>
        <td>
            <div class="label">Seller</div>
            <strong>{{ $invoice->seller_snapshot['company_name'] }}</strong><br>
            {{ $invoice->seller_snapshot['street'] }} {{ $invoice->seller_snapshot['house_number'] }}{{ $invoice->seller_snapshot['addition'] ?? '' }}<br>
            {{ $invoice->seller_snapshot['postal_code'] }} {{ $invoice->seller_snapshot['city'] }}, {{ $invoice->seller_snapshot['country_code'] }}<br>
            VAT: {{ $invoice->seller_snapshot['vat_number'] }}<br>
            Registration: {{ $invoice->seller_snapshot['registration_number'] }}
        </td>
        <td>
            <div class="label">Bill to</div>
            <strong>{{ $invoice->buyer_snapshot['company'] ?: ($invoice->buyer_snapshot['first_name'].' '.$invoice->buyer_snapshot['last_name']) }}</strong><br>
            {{ $invoice->buyer_snapshot['street'] }} {{ $invoice->buyer_snapshot['house_number'] }}{{ $invoice->buyer_snapshot['addition'] ?? '' }}<br>
            {{ $invoice->buyer_snapshot['postal_code'] }} {{ $invoice->buyer_snapshot['city'] }}, {{ $invoice->buyer_snapshot['country_code'] }}<br>
            {{ $invoice->buyer_snapshot['email'] }}
        </td>
    </tr>
</table>
<table class="columns meta">
    <tr>
        <td><span class="label">Document number</span><br><strong>{{ $invoice->document_number }}</strong></td>
        <td><span class="label">Issue date</span><br><strong>{{ $invoice->issued_at->format('d M Y') }}</strong></td>
    </tr>
    <tr>
        <td><span class="label">Order</span><br>{{ $invoice->order->order_number }}</td>
        <td><span class="label">Currency</span><br>{{ $invoice->currency }}</td>
    </tr>
</table>
@if($invoice->document_type->value === 'credit_note')
    <div class="notice">This credit note corrects invoice {{ $invoice->originalInvoice?->document_number }}. Amounts below are credited amounts.</div>
@endif
<table class="lines">
    <thead><tr><th>Description</th><th>SKU</th><th class="num">Qty</th><th class="num">Net</th><th class="num">VAT</th><th class="num">Total</th></tr></thead>
    <tbody>
    @foreach($invoice->lines as $line)
        <tr>
            <td><strong>{{ $line->description }}</strong>@if($line->options_snapshot)<div class="options">{{ collect($line->options_snapshot)->map(fn($v,$k) => $k.': '.$v)->join(' / ') }}</div>@endif</td>
            <td>{{ $line->sku ?: '-' }}</td>
            <td class="num">{{ $line->quantity }}</td>
            <td class="num">{{ $money->format($line->net_amount, $invoice->currency) }}</td>
            <td class="num">{{ $money->format($line->tax_amount, $invoice->currency) }}</td>
            <td class="num">{{ $money->format($line->total_amount, $invoice->currency) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
<table class="totals">
    <tr><td>Net</td><td class="num">{{ $money->format($invoice->net_amount, $invoice->currency) }}</td></tr>
    <tr><td>VAT ({{ number_format($invoice->tax_rate_basis_points / 100, 2) }}%)</td><td class="num">{{ $money->format($invoice->tax_amount, $invoice->currency) }}</td></tr>
    <tr class="grand"><td>{{ $invoice->document_type->value === 'credit_note' ? 'Total credit' : 'Total' }}</td><td class="num">{{ $money->format($invoice->total_amount, $invoice->currency) }}</td></tr>
</table>
</body>
</html>
