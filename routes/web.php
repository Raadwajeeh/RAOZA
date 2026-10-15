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
use App\Http\Controllers\AdminStoreController;
use App\Http\Controllers\AdminShippingCarrierController;
use App\Http\Controllers\AdminShipmentDocumentController;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\ConsentController;
use App\Http\Controllers\DemoPaymentController;
use App\Http\Controllers\CustomerAuthController;
use App\Http\Controllers\CustomerEmailVerificationController;
use App\Http\Controllers\CustomerAccountController;
use App\Http\Controllers\CustomerAddressController;
use App\Http\Controllers\CustomerReturnController;
use App\Http\Controllers\CustomerSettingsController;
use App\Http\Controllers\GuestOrderAccessController;
use App\Http\Controllers\AdminDocumentController;
use App\Http\Controllers\CustomerDocumentController;
use App\Http\Controllers\GuestDocumentController;
use App\Http\Controllers\AdminStaffController;
use App\Http\Controllers\AdminAuditController;
use App\Http\Controllers\AdminOperationsController;
use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Models\ContentPage;
use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Enums\CategoryStatus;
use App\Domain\Catalog\Enums\CollectionStatus;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Collection;
use App\Domain\Catalog\Models\Product;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

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
        ->merge(Category::query()->where('status', CategoryStatus::Active)->where('indexable', true)->get()->map(fn ($category) => [route('categories.show', $category), $category->updated_at]))
        ->merge(Collection::query()->where('status', CollectionStatus::Active)->where('indexable', true)->where(fn ($query) => $query->whereNull('published_at')->orWhere('published_at', '<=', now()))->get()->map(fn ($collection) => [route('collections.show', $collection), $collection->updated_at]))
        ->merge(ContentPage::query()->where('status', ContentStatus::Published)->where('indexable', true)->get()->map(fn ($x) => [route('content.show', $x->slug), $x->updated_at]));
    $xml = view('sitemap', compact('urls'))->render();
    return Response::make($xml, 200, ['Content-Type' => 'application/xml']);
})->name('sitemap');
Route::get('/robots.txt', function () {
    $lines = app()->environment('production')
        ? ['User-agent: *', 'Allow: /', 'Disallow: /admin', 'Disallow: /account', 'Disallow: /order-access', 'Disallow: /guest/orders', 'Disallow: /cart', 'Disallow: /checkout', 'Disallow: /order-confirmation', 'Disallow: /payments', 'Disallow: /demo', 'Sitemap: '.route('sitemap')]
        : ['User-agent: *', 'Disallow: /'];

    return Response::make(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
})->name('robots');

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
    Route::get('/account/login', [CustomerAuthController::class, 'login'])->name('account.login');
    Route::post('/account/login', [CustomerAuthController::class, 'authenticate'])->middleware('throttle:6,1')->name('account.authenticate');
    Route::get('/account/register', [CustomerAuthController::class, 'register'])->name('account.register');
    Route::post('/account/register', [CustomerAuthController::class, 'store'])->middleware('throttle:6,1')->name('account.store');
    Route::get('/account/forgot-password', [CustomerAuthController::class, 'forgotPassword'])->name('password.request');
    Route::post('/account/forgot-password', [CustomerAuthController::class, 'sendResetLink'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/account/reset-password/{token}', [CustomerAuthController::class, 'resetPassword'])->name('password.reset');
    Route::post('/account/reset-password', [CustomerAuthController::class, 'updatePassword'])->middleware('throttle:6,1')->name('password.update');
});
Route::middleware(['auth', 'customer'])->group(function () {
    Route::get('/account/verify-email', [CustomerEmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/account/verify-email/{id}/{hash}', [CustomerEmailVerificationController::class, 'verify'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/account/email/verification-notification', [CustomerEmailVerificationController::class, 'resend'])->middleware('throttle:6,1')->name('verification.send');
});
Route::middleware(['auth', 'customer', 'verified'])->group(function () {
    Route::get('/account', [CustomerAccountController::class, 'index'])->name('account.index');
    Route::get('/account/orders/{orderNumber}', [CustomerAccountController::class, 'show'])->name('account.orders.show');
    Route::get('/account/orders/{orderNumber}/documents/{documentNumber}', [CustomerDocumentController::class, 'download'])
        ->middleware('throttle:20,1')->name('account.orders.documents.download');
    Route::get('/account/addresses', [CustomerAddressController::class, 'index'])->name('account.addresses.index');
    Route::post('/account/addresses', [CustomerAddressController::class, 'store'])->middleware('throttle:20,1')->name('account.addresses.store');
    Route::patch('/account/addresses/{address}', [CustomerAddressController::class, 'update'])->middleware('throttle:20,1')->name('account.addresses.update');
    Route::delete('/account/addresses/{address}', [CustomerAddressController::class, 'destroy'])->middleware('throttle:20,1')->name('account.addresses.destroy');
    Route::patch('/account/addresses/{address}/default', [CustomerAddressController::class, 'setDefault'])->middleware('throttle:20,1')->name('account.addresses.default');
    Route::get('/account/returns', [CustomerReturnController::class, 'index'])->name('account.returns.index');
    Route::post('/account/orders/{orderNumber}/returns', [CustomerReturnController::class, 'store'])->middleware('throttle:5,1')->name('account.returns.store');
    Route::get('/account/settings', [CustomerSettingsController::class, 'edit'])->name('account.settings.edit');
    Route::patch('/account/settings/profile', [CustomerSettingsController::class, 'updateProfile'])->middleware('throttle:10,1')->name('account.settings.profile');
    Route::patch('/account/settings/password', [CustomerSettingsController::class, 'updatePassword'])->middleware('throttle:5,1')->name('account.settings.password');
});
Route::get('/order-access', [GuestOrderAccessController::class, 'request'])->name('guest-orders.request');
Route::post('/order-access', [GuestOrderAccessController::class, 'send'])->middleware('throttle:5,1')->name('guest-orders.send');
Route::get('/guest/orders/{token}', [GuestOrderAccessController::class, 'show'])->middleware('throttle:20,1')->name('guest-orders.show');
Route::get('/guest/orders/{token}/documents/{documentNumber}', [GuestDocumentController::class, 'download'])
    ->middleware('throttle:20,1')->name('guest-orders.documents.download');

Route::middleware('guest')->group(function () {
    Route::get('/admin/login', [AuthController::class, 'create'])->name('login');
    Route::post('/admin/login', [AuthController::class, 'store'])->middleware('throttle:6,1');
});
Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');
Route::middleware(['auth','admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', AdminDashboardController::class)->middleware('permission:dashboard.view')->name('dashboard');
    Route::get('/products', [AdminCatalogController::class, 'products'])->middleware('permission:products.view')->name('products.index');
    Route::get('/products/create', [AdminCatalogController::class, 'createProduct'])->middleware('permission:products.manage')->name('products.create');
    Route::post('/products', [AdminCatalogController::class, 'storeProduct'])->middleware('permission:products.manage')->name('products.store');
    Route::get('/products/{product}', [AdminCatalogController::class, 'showProduct'])->middleware('permission:products.view')->name('products.show');
    Route::patch('/products/{product}', [AdminCatalogController::class, 'updateProduct'])->middleware('permission:products.manage')->name('products.update');
    Route::post('/products/{product}/options', [AdminCatalogController::class, 'storeOption'])->middleware('permission:products.manage')->name('products.options.store');
    Route::post('/products/{product}/options/{option}/values', [AdminCatalogController::class, 'storeOptionValue'])->middleware('permission:products.manage')->name('products.option-values.store');
    Route::delete('/products/{product}/options/{option}', [AdminCatalogController::class, 'deleteOption'])->middleware('permission:products.manage')->name('products.options.destroy');
    Route::delete('/products/{product}/options/{option}/values/{value}', [AdminCatalogController::class, 'deleteOptionValue'])->middleware('permission:products.manage')->name('products.option-values.destroy');
    Route::post('/products/{product}/variants', [AdminCatalogController::class, 'storeVariant'])->middleware('permission:products.manage')->name('products.variants.store');
    Route::post('/products/{product}/variants/generate', [AdminCatalogController::class, 'generateVariants'])->middleware('permission:products.manage')->name('products.variants.generate');
    Route::patch('/products/{product}/variants/{variant}', [AdminCatalogController::class, 'updateVariant'])->middleware('permission:products.manage')->name('products.variants.update');
    Route::post('/products/{product}/images', [AdminCatalogController::class, 'storeImage'])->middleware('permission:products.manage')->name('products.images.store');
    Route::patch('/products/{product}/images/reorder', [AdminCatalogController::class, 'reorderImages'])->middleware('permission:products.manage')->name('products.images.reorder');
    Route::delete('/products/{product}/images/{image}', [AdminCatalogController::class, 'deleteImage'])->middleware('permission:products.manage')->name('products.images.destroy');
    Route::get('/categories', [AdminCatalogController::class, 'categories'])->middleware('permission:products.view')->name('categories.index');
    Route::post('/categories', [AdminCatalogController::class, 'storeCategory'])->middleware('permission:products.manage')->name('categories.store');
    Route::patch('/categories/{category}', [AdminCatalogController::class, 'updateCategory'])->middleware('permission:products.manage')->name('categories.update');
    Route::get('/collections', [AdminCatalogController::class, 'collections'])->middleware('permission:products.view')->name('collections.index');
    Route::post('/collections', [AdminCatalogController::class, 'storeCollection'])->middleware('permission:products.manage')->name('collections.store');
    Route::patch('/collections/{collection}', [AdminCatalogController::class, 'updateCollection'])->middleware('permission:products.manage')->name('collections.update');
    Route::get('/inventory', [AdminInventoryController::class, 'index'])->middleware('permission:inventory.view')->name('inventory.index');
    Route::patch('/inventory/{variant}', [AdminInventoryController::class, 'adjust'])->middleware(['permission:inventory.adjust','throttle:30,1'])->name('inventory.adjust');
    Route::get('/orders', [AdminOrdersController::class, 'index'])->middleware('permission:orders.view')->name('orders.index');
    Route::get('/orders/{order:order_number}', [AdminOrdersController::class, 'show'])->middleware('permission:orders.view')->name('orders.show');
    Route::get('/orders/{order:order_number}/documents/accounting/{documentNumber}', [AdminDocumentController::class, 'accounting'])->middleware('permission:orders.view')->name('orders.documents.accounting');
    Route::get('/orders/{order:order_number}/documents/operational/{type}', [AdminDocumentController::class, 'operational'])->middleware(['permission:orders.view','throttle:30,1'])->name('orders.documents.operational');
    Route::post('/orders/{order:order_number}/cancel', [AdminOrdersController::class, 'cancel'])->middleware(['permission:orders.manage','throttle:20,1'])->name('orders.cancel');
    Route::get('/customers', [AdminCustomersController::class, 'index'])->middleware('permission:customers.view')->name('customers.index');
    Route::get('/customers/{email}', [AdminCustomersController::class, 'show'])->middleware('permission:customers.view')->where('email','.*')->name('customers.show');
    Route::get('/content', [AdminContentController::class, 'index'])->middleware('permission:content.manage')->name('content.index');
    Route::post('/content/pages', [AdminContentController::class, 'storePage'])->middleware('permission:content.manage')->name('content.pages.store');
    Route::patch('/content/pages/{page}', [AdminContentController::class, 'updatePage'])->middleware('permission:content.manage')->name('content.pages.update');
    Route::get('/discounts', [AdminDiscountsController::class, 'index'])->middleware('permission:discounts.manage')->name('discounts.index');
    Route::post('/discounts', [AdminDiscountsController::class, 'store'])->middleware('permission:discounts.manage')->name('discounts.store');
    Route::patch('/discounts/{discount}', [AdminDiscountsController::class, 'update'])->middleware('permission:discounts.manage')->name('discounts.update');
    Route::patch('/discounts/{discount}/toggle', [AdminDiscountsController::class, 'toggle'])->middleware('permission:discounts.manage')->name('discounts.toggle');
    Route::get('/store', [AdminStoreController::class, 'index'])->middleware('permission:content.manage')->name('store.index');
    Route::patch('/store', [AdminStoreController::class, 'updateStore'])->middleware('permission:content.manage')->name('store.update');
    Route::post('/store/shipping-methods', [AdminStoreController::class, 'storeShipping'])->middleware('permission:content.manage')->name('shipping-methods.store');
    Route::patch('/store/shipping-methods/{shippingMethod}', [AdminStoreController::class, 'updateShipping'])->middleware('permission:content.manage')->name('shipping-methods.update');
    Route::get('/fulfillment', [AdminFulfillmentController::class, 'index'])->middleware('permission:fulfillment.manage')->name('fulfillment.index');
    Route::patch('/orders/{order:order_number}/fulfillment', [AdminFulfillmentController::class, 'transition'])->middleware('permission:fulfillment.manage')->name('fulfillment.transition');
    Route::post('/orders/{order:order_number}/shipments', [AdminFulfillmentController::class, 'ship'])->middleware(['permission:fulfillment.manage','throttle:30,1'])->name('shipments.store');
    Route::patch('/shipments/{shipment}', [AdminFulfillmentController::class, 'update'])->middleware(['permission:fulfillment.manage','throttle:30,1'])->name('shipments.update');
    Route::post('/shipments/{shipment}/hand-over', [AdminFulfillmentController::class, 'handOver'])->middleware(['permission:fulfillment.manage','throttle:20,1'])->name('shipments.hand-over');
    Route::patch('/shipments/{shipment}/status', [AdminFulfillmentController::class, 'status'])->middleware(['permission:fulfillment.manage','throttle:30,1'])->name('shipments.status');
    Route::post('/shipments/{shipment}/cancel', [AdminFulfillmentController::class, 'cancel'])->middleware(['permission:fulfillment.manage','throttle:20,1'])->name('shipments.cancel');
    Route::get('/shipments/{shipment}/documents/manual-address-label', [AdminShipmentDocumentController::class, 'label'])->middleware(['permission:fulfillment.manage','throttle:30,1'])->name('shipments.documents.label');
    Route::post('/shipping-carriers', [AdminShippingCarrierController::class, 'store'])->middleware(['permission:fulfillment.manage','throttle:20,1'])->name('shipping-carriers.store');
    Route::patch('/shipping-carriers/{shippingCarrier}', [AdminShippingCarrierController::class, 'update'])->middleware(['permission:fulfillment.manage','throttle:20,1'])->name('shipping-carriers.update');
    Route::get('/returns', [AdminReturnsController::class, 'index'])->middleware('permission:returns.view')->name('returns.index');
    Route::post('/orders/{order:order_number}/returns', [AdminReturnsController::class, 'store'])->middleware(['permission:returns.manage','throttle:20,1'])->name('returns.store');
    Route::patch('/returns/{return:return_number}', [AdminReturnsController::class, 'transition'])->middleware('permission:returns.manage')->name('returns.transition');
    Route::patch('/return-items/{returnItem}/inspection', [AdminReturnsController::class, 'inspect'])->middleware('permission:returns.manage')->name('returns.inspect');
    Route::post('/orders/{order:order_number}/refunds', [AdminReturnsController::class, 'refund'])->middleware(['permission:refunds.manage','throttle:10,1'])->name('refunds.store');
    Route::post('/refunds/{refund}/sync', [AdminReturnsController::class, 'syncRefund'])->middleware(['permission:refunds.manage','throttle:20,1'])->name('refunds.sync');
    Route::get('/staff', [AdminStaffController::class, 'index'])->middleware('permission:staff.manage')->name('staff.index');
    Route::post('/staff', [AdminStaffController::class, 'store'])->middleware(['permission:staff.manage','throttle:5,1'])->name('staff.store');
    Route::patch('/staff/{staff}', [AdminStaffController::class, 'update'])->middleware(['permission:staff.manage','throttle:10,1'])->name('staff.update');
    Route::get('/audit', AdminAuditController::class)->middleware('permission:audit.view')->name('audit.index');
    Route::get('/operations', [AdminOperationsController::class, 'index'])->middleware('permission:operations.view')->name('operations.index');
    Route::post('/operations/payments/{payment}/retry', [AdminOperationsController::class, 'retryPayment'])->middleware(['permission:operations.manage','throttle:5,1'])->name('operations.payments.retry');
    Route::post('/operations/invoices/{invoice}/regenerate-pdf', [AdminOperationsController::class, 'regeneratePdf'])->middleware(['permission:operations.manage','throttle:5,1'])->name('operations.invoices.regenerate-pdf');
});
