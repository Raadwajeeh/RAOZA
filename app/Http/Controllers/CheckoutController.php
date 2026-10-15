<?php

namespace App\Http\Controllers;

use App\Domain\Commerce\Models\Order;
use App\Domain\Commerce\Services\CartService;
use App\Domain\Commerce\Services\CheckoutService;
use App\Domain\Commerce\Services\OrderPresenter;
use App\Domain\Customer\Models\CustomerAddress;
use App\Domain\Fulfillment\Services\ShippingQuoteService;
use App\Domain\Marketing\Services\AnalyticsService;
use App\Http\Middleware\ResolveCart;
use App\Mail\OrderReceivedMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Services\TransactionalNotificationService;
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

        $user = $request->user();
        $addresses = $user && ! $user->isStaff()
            ? $user->addresses()->orderByDesc('is_default_shipping')->orderByDesc('is_default_billing')->get()->map(fn (CustomerAddress $address) => $address->only([
                'id', 'label', 'first_name', 'last_name', 'company', 'phone', 'street', 'house_number', 'addition',
                'postal_code', 'city', 'country_code', 'is_shipping', 'is_billing', 'is_default_shipping', 'is_default_billing',
            ]))->values()
            : collect();

        return Inertia::render('Storefront/Checkout', ['checkout' => [
            'cart' => $summary,
            'countries' => [['code' => 'NL', 'name' => 'Netherlands']],
            'shippingMethods' => $shipping->active(),
            'vatRateBasisPoints' => (int) config('commerce.vat_rate_basis_points', 2100),
            'savedAddresses' => $addresses,
        ]]);
    }

    public function store(Request $request, CheckoutService $service): RedirectResponse
    {
        $this->hydrateSavedAddresses($request);
        $data = $request->validate($this->rules());
        $data['_analytics_consent'] = (bool) $request->session()->get('consent.analytics', false);
        $data['_marketing_consent'] = (bool) $request->session()->get('consent.marketing', false);
        $order = $service->createOrder(ResolveCart::from($request), $data);
        $request->session()->put('last_order_number', $order->order_number);

        if ($order->wasRecentlyCreated) {
            app(TransactionalNotificationService::class)->queueOnce('order.received:'.$order->id, $order->customer_email, new OrderReceivedMail($order), $order);
        }

        return redirect()->route('checkout.confirmation', ['order' => $order->order_number]);
    }

    public function confirmation(Request $request, Order $order, OrderPresenter $presenter): Response
    {
        abort_unless($this->mayAccess($request, $order), 404);

        return Inertia::render('Storefront/OrderConfirmation', [
            'order' => $presenter->detail($order),
        ]);
    }

    private function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc', 'max:254'], 'phone' => ['nullable', 'string', 'max:40'],
            'shipping_address_id' => ['nullable', 'integer'], 'billing_address_id' => ['nullable', 'integer'],
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

    private function hydrateSavedAddresses(Request $request): void
    {
        $this->hydrateSavedAddress($request, 'shipping');
        if (! $request->boolean('billing_same_as_shipping')) {
            $this->hydrateSavedAddress($request, 'billing');
        }
    }

    private function hydrateSavedAddress(Request $request, string $type): void
    {
        $addressId = $request->input("{$type}_address_id");
        if ($addressId === null || $addressId === '') {
            return;
        }

        $user = $request->user();
        abort_unless($user && ! $user->isStaff(), 404);
        $address = CustomerAddress::query()
            ->where('user_id', $user->id)
            ->where("is_{$type}", true)
            ->findOrFail((int) $addressId);
        $request->merge([
            "{$type}_first_name" => $address->first_name,
            "{$type}_last_name" => $address->last_name,
            "{$type}_company" => $address->company,
            "{$type}_street" => $address->street,
            "{$type}_house_number" => $address->house_number,
            "{$type}_addition" => $address->addition,
            "{$type}_postal_code" => $address->postal_code,
            "{$type}_city" => $address->city,
            "{$type}_country_code" => $address->country_code,
        ]);
    }

    private function mayAccess(Request $request, Order $order): bool
    {
        if ($request->session()->get('last_order_number') === $order->order_number) {
            return true;
        }

        $guestAccessExpiresAt = (int) $request->session()->get("guest_order_access.{$order->order_number}", 0);
        if ($guestAccessExpiresAt > now()->getTimestamp() && $order->user_id === null) {
            return true;
        }

        $user = $request->user();

        return $user && ! $user->isStaff() && $order->user_id === $user->id;
    }
}
