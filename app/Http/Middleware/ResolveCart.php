<?php
namespace App\Http\Middleware;

use App\Domain\Commerce\Enums\CartStatus;
use App\Domain\Commerce\Models\Cart;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ResolveCart
{
    public const COOKIE = 'raoza_cart';

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->cookie(self::COOKIE);
        $cart = $token ? Cart::query()->where('token',$token)->where('status',CartStatus::Active)->where(fn($q)=>$q->whereNull('expires_at')->orWhere('expires_at','>',now()))->first() : null;
        if (!$cart) {
            $cart = Cart::create(['token'=>(string)Str::uuid(),'user_id'=>$request->user()?->id,'status'=>CartStatus::Active,'currency'=>'EUR','expires_at'=>now()->addDays(30)]);
        } elseif ($request->user() && !$cart->user_id) {
            $cart->update(['user_id'=>$request->user()->id]);
        }
        $request->attributes->set('cart',$cart);
        $response=$next($request);
        if ($token !== $cart->token) $response->headers->setCookie(cookie(self::COOKIE,$cart->token,60*24*30,'/',null,(bool) config('session.secure_cookie', false),true,false,'lax'));
        return $response;
    }

    public static function from(Request $request): Cart
    {
        return $request->attributes->get('cart') ?? throw new \LogicException('Cart middleware is missing.');
    }
}
