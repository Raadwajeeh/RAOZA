<?php

namespace App\Domain\Commerce\Services;

use App\Domain\Commerce\Models\Order;

class OrderPresenter
{
    public function detail(Order $order): array
    {
        $order->loadMissing(['items', 'payments', 'addresses', 'shipments.items.orderItem']);
        $address = $order->addresses->firstWhere('type', 'shipping');
        $publicShipments = $order->shipments->filter(fn ($shipment) => in_array($shipment->status->value, ['shipped', 'in_transit', 'delivered', 'exception', 'returned'], true))->sortBy('id')->values();
        $latestShipment = $publicShipments->last();

        return [
            'number' => $order->order_number,
            'email' => $order->customer_email,
            'placedAt' => $order->placed_at?->toIso8601String(),
            'currency' => $order->currency,
            'subtotal' => $order->subtotal_amount,
            'discount' => $order->discount_amount,
            'discountCode' => $order->discount_code,
            'shipping' => $order->shipping_amount,
            'shippingMethod' => $order->shipping_method_name,
            'tax' => $order->tax_amount,
            'taxRateBasisPoints' => $order->tax_rate_basis_points,
            'total' => $order->total_amount,
            'paymentStatus' => $order->payment_status->value,
            'orderStatus' => $order->order_status->value,
            'fulfillmentStatus' => $order->fulfillment_status->value,
            'canPay' => $order->payment_status->canAcceptPayment(),
            'latestPaymentStatus' => $order->payments->sortByDesc('id')->first()?->status?->value,
            'tracking' => $latestShipment ? [
                'number' => $latestShipment->tracking_number,
                'url' => $latestShipment->tracking_url,
                'status' => $latestShipment->status->value,
            ] : null,
            'shipments' => $publicShipments->map(fn ($shipment) => [
                'id' => $shipment->id,
                'carrier' => $shipment->carrier_name ?: 'Manual carrier',
                'status' => $shipment->status->value,
                'trackingNumber' => $shipment->tracking_number,
                'trackingUrl' => $shipment->tracking_url,
                'shippedAt' => $shipment->shipped_at?->toIso8601String(),
                'deliveredAt' => $shipment->delivered_at?->toIso8601String(),
                'items' => $shipment->items->map(fn ($item) => [
                    'name' => $item->orderItem->product_name,
                    'sku' => $item->orderItem->sku,
                    'quantity' => $item->quantity,
                ])->values(),
            ]),
            'shippingAddress' => $address?->only('first_name', 'last_name', 'company', 'street', 'house_number', 'addition', 'postal_code', 'city', 'country_code'),
            'items' => $order->items->map(fn ($item) => [
                'name' => $item->product_name,
                'sku' => $item->sku,
                'options' => $item->variant_options,
                'quantity' => $item->quantity,
                'unitPrice' => $item->unit_price,
                'total' => $item->total_amount,
            ])->values(),
        ];
    }
}
