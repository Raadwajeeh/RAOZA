<?php

namespace App\Http\Controllers;

use App\Domain\Commerce\Models\Order;
use App\Domain\Commerce\Services\GuestOrderAccessService;
use App\Domain\Commerce\Services\OrderPresenter;
use App\Jobs\SendGuestOrderAccessLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GuestOrderAccessController extends Controller
{
    public function request(): Response
    {
        return Inertia::render('GuestOrders/RequestAccess');
    }

    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'order_number' => ['required', 'string', 'max:32'],
            'email' => ['required', 'email:rfc', 'max:254'],
        ]);

        $order = Order::query()
            ->whereNull('user_id')
            ->where('order_number', trim($data['order_number']))
            ->whereRaw('LOWER(customer_email) = ?', [mb_strtolower(trim($data['email']))])
            ->first();

        if ($order) {
            // The queued payload contains only the order id. The raw bearer token is generated in the worker.
            SendGuestOrderAccessLink::dispatch($order->id)->afterCommit();
        }

        return redirect()->route('guest-orders.request')->with(
            'success',
            'If the order details match a guest order, a temporary access link has been sent.'
        );
    }

    public function show(Request $request, string $token, GuestOrderAccessService $access, OrderPresenter $presenter): Response
    {
        $resolved = $access->resolve($token);
        $order = $resolved['order'];
        $request->session()->put(
            "guest_order_access.{$order->order_number}",
            $resolved['expires_at']->getTimestamp(),
        );

        return Inertia::render('Storefront/OrderConfirmation', [
            'order' => [...$presenter->detail($order), 'documents' => $order->invoices()->orderByDesc('issued_at')->get()->map(fn ($document) => [
                'number' => $document->document_number,
                'type' => $document->document_type->value,
                'issuedAt' => $document->issued_at?->toIso8601String(),
                'total' => $document->total_amount,
                'currency' => $document->currency,
                'url' => route('guest-orders.documents.download', [$token, $document->document_number]),
            ])->values()],
            'guestAccess' => true,
        ]);
    }
}
