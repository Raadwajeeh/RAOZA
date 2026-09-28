<?php
namespace App\Http\Middleware;
use App\Domain\Catalog\Enums\{CategoryStatus,CollectionStatus};
use App\Domain\Catalog\Models\{Category,Collection};
use App\Domain\Commerce\Models\Cart;
use Illuminate\Http\Request;
use Inertia\Middleware;
class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';
    public function share(Request $request): array
    {
        /** @var Cart|null $cart */
        $cart=$request->attributes->get('cart');
        return [...parent::share($request),
            'auth'=>['user'=>$request->user()?->only('id','name','email','role')],
            'cart'=>['count'=>$cart?->items()->sum('quantity') ?? 0],
            'navigation'=>fn()=>[
                'categories'=>Category::query()->where('status',CategoryStatus::Active)->orderBy('position')->limit(4)->get(['name','slug'])->toArray(),
                'collections'=>Collection::query()->where('status',CollectionStatus::Active)->where(fn($q)=>$q->whereNull('published_at')->orWhere('published_at','<=',now()))->latest('published_at')->limit(4)->get(['name','slug'])->toArray(),
            ],
            'consent'=>['decided'=>$request->session()->has('consent.id'),'analytics'=>(bool)$request->session()->get('consent.analytics',false),'marketing'=>(bool)$request->session()->get('consent.marketing',false)],
            'demo'=>['enabled'=>(bool)config('commerce.demo_mode')],
            'flash'=>['success'=>fn()=>$request->session()->get('success'),'errors'=>fn()=>$request->session()->get('errors')?->getBag('default')->getMessages() ?? []],
        ];
    }
}
