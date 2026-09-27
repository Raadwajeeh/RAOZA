<?php
namespace App\Http\Controllers;

use App\Domain\Commerce\Models\CartItem;
use App\Domain\Commerce\Services\CartService;
use App\Http\Middleware\ResolveCart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    public function show(Request $request, CartService $service): Response
    {
        return Inertia::render('Storefront/Cart', ['cart'=>$service->summary(ResolveCart::from($request))]);
    }
    public function store(Request $request, CartService $service): RedirectResponse
    {
        $data=$request->validate(['variant_id'=>['required','integer'],'quantity'=>['required','integer','min:1','max:20']]);
        $service->add(ResolveCart::from($request),(int)$data['variant_id'],(int)$data['quantity']);
        return back()->with('success','Added to bag.');
    }
    public function update(Request $request, CartItem $cartItem, CartService $service): RedirectResponse
    {
        $data=$request->validate(['quantity'=>['required','integer','min:1','max:20']]);
        $service->update(ResolveCart::from($request),$cartItem,(int)$data['quantity']);
        return back();
    }
    public function destroy(Request $request, CartItem $cartItem, CartService $service): RedirectResponse
    {
        $service->remove(ResolveCart::from($request),$cartItem);
        return back();
    }
}
