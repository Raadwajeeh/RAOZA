<?php

namespace App\Http\Controllers;

use App\Domain\Commerce\Models\Order;
use App\Domain\Commerce\Services\CartService;
use App\Domain\Commerce\Services\CheckoutService;
use App\Domain\Fulfillment\Services\ShippingQuoteService;
use App\Domain\Marketing\Services\AnalyticsService;
use App\Http\Middleware\ResolveCart;
use App\Mail\OrderReceivedMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    public function show(Request $request, CartService $cartService, ShippingQuoteService $shipping, AnalyticsService $analytics): Response|RedirectResponse
    {
        $summary = $cartService->summary(ResolveCart::from($request));
        if ($summary['items']->isEmpty()) {
            return redirect()->route('cart.show');
        }

        $analytics->record($request, 'begin_checkout', [
            'currency' => $summary['currency'],
            'value_minor' => $summary['subtotal'],
            'item_count' => $summary['count'],
        ]);

        return Inertia::render('Storefront/Checkout', ['checkout' => [
            'cart' => $summary,
            'countries' => [['code' => 'NL', 'name' => 'Netherlands']],
            'shippingMethods' => $shipping->active(),
            'vatRateBasisPoints' => (int) config('commerce.vat_rate_basis_points', 2100),
        ]]);
    }

    public function store(Request $request, CheckoutService $service): RedirectResponse
    {
        $data = $request->validate($this->rules());
        $data['_analytics_consent'] = (bool) $request->session()->get('consent.analytics', false);
        $data['_marketing_consent'] = (bool) $request->session()->get('consent.marketing', false);
        $order = $service->createOrder(ResolveCart::from($request), $data);
        $request->session()->put('last_order_number', $order->order_number);

        if ($order->wasRecentlyCreated) {
            Mail::to($order->customer_email)->queue(new OrderReceivedMail($order));
        }

        return redirect()->route('checkout.confirmation', ['order' => $order->order_number]);
    }

    public function confirmation(Request $request, Order $order): Response
    {
        abort_unless($request->session()->get('last_order_number') === $order->order_number, 404);

        return Inertia::render('Storefront/OrderConfirmation', [
            'order' => $this->present($order->load(['items', 'payments', 'addresses'])),
        ]);
    }

    private function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc', 'max:254'], 'phone' => ['nullable', 'string', 'max:40'],
            'shipping_method_id' => ['required', 'integer', 'exists:shipping_methods,id'], 'discount_code' => ['nullable', 'string', 'max:50'],
            'billing_same_as_shipping' => ['required', 'boolean'],
            'shipping_first_name' => ['required', 'string', 'max:100'], 'shipping_last_name' => ['required', 'string', 'max:100'],
            'shipping_company' => ['nullable', 'string', 'max:150'], 'shipping_street' => ['required', 'string', 'max:150'],
            'shipping_house_number' => ['required', 'string', 'max:32'], 'shipping_addition' => ['nullable', 'string', 'max:32'],
            'shipping_postal_code' => ['required', 'string', 'max:24'], 'shipping_city' => ['required', 'string', 'max:120'],
            'shipping_country_code' => ['required', Rule::in(['NL'])],
            'billing_first_name' => ['exclude_if:billing_same_as_shipping,true', 'required', 'string', 'max:100'],
            'billing_last_name' => ['exclude_if:billing_same_as_shipping,true', 'required', 'string', 'max:100'],
            'billing_company' => ['nullable', 'string', 'max:150'],
            'billing_street' => ['exclude_if:billing_same_as_shipping,true', 'required', 'string', 'max:150'],
            'billing_house_number' => ['exclude_if:billing_same_as_shipping,true', 'required', 'string', 'max:32'],
            'billing_addition' => ['nullable', 'string', 'max:32'],
            'billing_postal_code' => ['exclude_if:billing_same_as_shipping,true', 'required', 'string', 'max:24'],
            'billing_city' => ['exclude_if:billing_same_as_shipping,true', 'required', 'string', 'max:120'],
            'billing_country_code' => ['exclude_if:billing_same_as_shipping,true', 'required', Rule::in(['NL'])],
        ];
    }

    private function present(Order $order): array
    {
        $address = $order->addresses->firstWhere('type', 'shipping');

        return [
            'number' => $order->order_number, 'email' => $order->customer_email, 'currency' => $order->currency,
            'subtotal' => $order->subtotal_amount, 'discount' => $order->discount_amount, 'discountCode' => $order->discount_code,
            'shipping' => $order->shipping_amount, 'shippingMethod' => $order->shipping_method_name, 'tax' => $order->tax_amount,
            'taxRateBasisPoints' => $order->tax_rate_basis_points, 'total' => $order->total_amount,
            'paymentStatus' => $order->payment_status->value, 'orderStatus' => $order->order_status->value,
            'fulfillmentStatus' => $order->fulfillment_status->value, 'canPay' => $order->payment_status->canAcceptPayment(),
            'latestPaymentStatus' => $order->payments->sortByDesc('id')->first()?->status?->value,
            'shippingAddress' => $address ? $address->only('first_name', 'last_name', 'company', 'street', 'house_number', 'addition', 'postal_code', 'city', 'country_code') : null,
            'items' => $order->items->map(fn ($item) => ['name' => $item->product_name, 'options' => $item->variant_options, 'quantity' => $item->quantity, 'total' => $item->total_amount])->values(),
        ];
    }
}
