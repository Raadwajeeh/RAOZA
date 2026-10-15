<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><style>
    @page { margin: 34px 42px; } body { font-family: "DejaVu Sans", sans-serif; color:#241219; font-size:11px; }
    .head { border-bottom:3px solid #330313; padding-bottom:14px; margin-bottom:20px; } .brand { font-family:serif; letter-spacing:4px; font-size:24px; }
    h1 { float:right; margin:3px 0; font-size:17px; color:#602032; text-transform:uppercase; } .meta { color:#6f5963; margin:10px 0 22px; }
    table { width:100%; border-collapse:collapse; } th { background:#330313; color:#fff6e6; text-align:left; padding:8px; font-size:9px; } td { border-bottom:1px solid #eadfe3; padding:9px 8px; }
    .qty { text-align:center; font-size:14px; font-weight:bold; } .box { border:1px solid #d9cbd0; padding:12px; margin:14px 0 22px; }
    .muted { color:#806c74; font-size:9px; }
</style></head>
<body>
<div class="head"><h1>{{ str_replace('-', ' ', $type) }}</h1><div class="brand">RAOZA</div></div>
<div class="meta">Order {{ $order->order_number }} &nbsp; | &nbsp; Created {{ now()->format('d M Y H:i') }}</div>
@if(in_array($type, ['packing-slip', 'order-sheet'], true))
    @php($address = $order->addresses->firstWhere('type', 'shipping'))
    <div class="box"><strong>Ship to</strong><br>{{ $address->first_name }} {{ $address->last_name }}@if($address->company)<br>{{ $address->company }}@endif<br>{{ $address->street }} {{ $address->house_number }}{{ $address->addition }}<br>{{ $address->postal_code }} {{ $address->city }}, {{ $address->country_code }}</div>
@endif
@if($type === 'order-sheet')
    <div class="box"><strong>Workflow</strong><br>Order: {{ $order->order_status->value }} &nbsp; | &nbsp; Fulfillment: {{ $order->fulfillment_status->value }} &nbsp; | &nbsp; Shipping method: {{ $order->shipping_method_name }}</div>
@endif
<table><thead><tr><th>SKU</th><th>Item</th><th>Options</th><th class="qty">Quantity</th></tr></thead><tbody>
@foreach($order->items as $item)<tr><td>{{ $item->sku }}</td><td>{{ $item->product_name }}</td><td>{{ collect($item->variant_options ?? [])->map(fn($v,$k) => $k.': '.$v)->join(' / ') ?: '-' }}</td><td class="qty">{{ $item->quantity }}</td></tr>@endforeach
</tbody></table>
@if($type === 'packing-slip')<p class="muted">Prices and payment details are intentionally omitted from this packing document.</p>@endif
@if($type === 'picking-list')<p class="muted">Customer identity, address, prices and payment details are intentionally omitted from this warehouse document.</p>@endif
</body></html>
