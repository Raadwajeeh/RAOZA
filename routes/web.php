<?php
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\StorefrontController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\AdminFulfillmentController;
use App\Http\Controllers\AdminReturnsController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminCatalogController;
use App\Http\Controllers\AdminInventoryController;
use App\Http\Controllers\AdminOrdersController;
use App\Http\Controllers\AdminCustomersController;
use App\Http\Controllers\AdminDiscountsController;
use App\Http\Controllers\AdminContentController;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\ConsentController;
use App\Http\Controllers\DemoPaymentController;
use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Models\ContentPage;
use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Product;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/health/live', [HealthController::class, 'live'])->name('health.live');
Route::get('/health/ready', [HealthController::class, 'ready'])->middleware('throttle:60,1')->name('health.ready');

Route::get('/', [StorefrontController::class, 'home'])->name('home');
Route::get('/shop', [StorefrontController::class, 'shop'])->name('shop');
Route::get('/categories/{category:slug}', [StorefrontController::class, 'category'])->name('categories.show');
Route::get('/collections/{collection:slug}', [StorefrontController::class, 'collection'])->name('collections.show');
Route::get('/products/{product:slug}', [StorefrontController::class, 'product'])->name('products.show');

Route::post('/consent', [ConsentController::class, 'store'])->middleware('throttle:20,1')->name('consent.store');
Route::get('/pages/{slug}', [ContentController::class, 'show'])->name('content.show');
Route::get('/sitemap.xml', function () {
    $urls = collect([[route('home'), now()], [route('shop'), now()]])
        ->merge(Product::query()->where('status', ProductStatus::Active)->where('indexable', true)->get()->map(fn ($p) => [route('products.show', $p), $p->updated_at]))
        ->merge(ContentPage::query()->where('status', ContentStatus::Published)->where('indexable', true)->get()->map(fn ($x) => [route('content.show', $x->slug), $x->updated_at]));
    $xml = view('sitemap', compact('urls'))->render();
    return Response::make($xml, 200, ['Content-Type' => 'application/xml']);
})->name('sitemap');

Route::get('/cart', [CartController::class, 'show'])->name('cart.show');
Route::post('/cart/items', [CartController::class, 'store'])->middleware('throttle:60,1')->name('cart.items.store');
Route::patch('/cart/items/{cartItem}', [CartController::class, 'update'])->middleware('throttle:60,1')->name('cart.items.update');
Route::delete('/cart/items/{cartItem}', [CartController::class, 'destroy'])->middleware('throttle:60,1')->name('cart.items.destroy');
Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:10,1')->name('checkout.store');
Route::get('/order-confirmation/{order:order_number}', [CheckoutController::class, 'confirmation'])->name('checkout.confirmation');
Route::post('/orders/{order:order_number}/pay', [PaymentController::class, 'start'])->middleware('throttle:10,1')->name('payments.start');
Route::get('/payments/return/{order:order_number}', [PaymentController::class, 'returned'])->name('payments.return');
Route::post('/payments/webhook', [PaymentController::class, 'webhook'])->middleware('throttle:120,1')->name('payments.webhook');
Route::get('/demo/payments/{providerPaymentId}', [DemoPaymentController::class, 'show'])->name('demo.payment.show');
Route::post('/demo/payments/{providerPaymentId}', [DemoPaymentController::class, 'complete'])->middleware('throttle:30,1')->name('demo.payment.complete');

Route::middleware('guest')->group(function () {
    Route::get('/admin/login', [AuthController::class, 'create'])->name('login');
    Route::post('/admin/login', [AuthController::class, 'store'])->middleware('throttle:6,1');
});
Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');
Route::middleware(['auth','admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', AdminDashboardController::class)->middleware('permission:dashboard.view')->name('dashboard');
    Route::get('/products', [AdminCatalogController::class, 'products'])->middleware('permission:products.view')->name('products.index');
    Route::post('/products', [AdminCatalogController::class, 'storeProduct'])->middleware('permission:products.manage')->name('products.store');
    Route::patch('/products/{product}', [AdminCatalogController::class, 'updateProduct'])->middleware('permission:products.manage')->name('products.update');
    Route::get('/categories', [AdminCatalogController::class, 'categories'])->middleware('permission:products.view')->name('categories.index');
    Route::post('/categories', [AdminCatalogController::class, 'storeCategory'])->middleware('permission:products.manage')->name('categories.store');
    Route::get('/collections', [AdminCatalogController::class, 'collections'])->middleware('permission:products.view')->name('collections.index');
    Route::post('/collections', [AdminCatalogController::class, 'storeCollection'])->middleware('permission:products.manage')->name('collections.store');
    Route::get('/inventory', [AdminInventoryController::class, 'index'])->middleware('permission:inventory.view')->name('inventory.index');
    Route::patch('/inventory/{variant}', [AdminInventoryController::class, 'adjust'])->middleware(['permission:inventory.adjust','throttle:30,1'])->name('inventory.adjust');
    Route::get('/orders', [AdminOrdersController::class, 'index'])->middleware('permission:orders.view')->name('orders.index');
    Route::get('/orders/{order:order_number}', [AdminOrdersController::class, 'show'])->middleware('permission:orders.view')->name('orders.show');
    Route::get('/customers', [AdminCustomersController::class, 'index'])->middleware('permission:customers.view')->name('customers.index');
    Route::get('/content', [AdminContentController::class, 'index'])->middleware('permission:content.manage')->name('content.index');
    Route::post('/content/pages', [AdminContentController::class, 'storePage'])->middleware('permission:content.manage')->name('content.pages.store');
    Route::patch('/content/pages/{page}', [AdminContentController::class, 'updatePage'])->middleware('permission:content.manage')->name('content.pages.update');
    Route::get('/discounts', [AdminDiscountsController::class, 'index'])->middleware('permission:discounts.manage')->name('discounts.index');
    Route::post('/discounts', [AdminDiscountsController::class, 'store'])->middleware('permission:discounts.manage')->name('discounts.store');
    Route::patch('/discounts/{discount}/toggle', [AdminDiscountsController::class, 'toggle'])->middleware('permission:discounts.manage')->name('discounts.toggle');
    Route::get('/fulfillment', [AdminFulfillmentController::class, 'index'])->middleware('permission:fulfillment.manage')->name('fulfillment.index');
    Route::patch('/orders/{order:order_number}/fulfillment', [AdminFulfillmentController::class, 'transition'])->middleware('permission:fulfillment.manage')->name('fulfillment.transition');
    Route::post('/orders/{order:order_number}/shipments', [AdminFulfillmentController::class, 'ship'])->middleware(['permission:fulfillment.manage','throttle:30,1'])->name('shipments.store');
    Route::get('/returns', [AdminReturnsController::class, 'index'])->middleware('permission:returns.view')->name('returns.index');
    Route::patch('/returns/{return:return_number}', [AdminReturnsController::class, 'transition'])->middleware('permission:returns.manage')->name('returns.transition');
    Route::patch('/return-items/{returnItem}/inspection', [AdminReturnsController::class, 'inspect'])->middleware('permission:returns.manage')->name('returns.inspect');
    Route::post('/orders/{order:order_number}/refunds', [AdminReturnsController::class, 'refund'])->middleware(['permission:refunds.manage','throttle:10,1'])->name('refunds.store');
});
