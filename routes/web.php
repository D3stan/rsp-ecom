<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProductsController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/products', [ProductsController::class, 'index'])->name('products');
Route::get('/products/{slug}', [ProductsController::class, 'show'])->name('products.show');

// Cart routes
Route::get('/cart', [CartController::class, 'index'])->name('cart');
Route::post('/cart/add', [CartController::class, 'add'])->name('cart.add');
Route::put('/cart/items/{cartItem}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/cart/items/{cartItem}', [CartController::class, 'remove'])->name('cart.remove');
Route::delete('/cart/clear', [CartController::class, 'clear'])->name('cart.clear');
Route::get('/cart/count', [CartController::class, 'count'])->name('cart.count');
Route::post('/cart/apply-coupon', [CartController::class, 'applyCoupon'])->name('cart.apply-coupon');

// CSRF token endpoint for AJAX requests
Route::get('/csrf-token', function () {
    return response()->json(['token' => csrf_token()]);
})->name('csrf.token');

// Checkout routes (authenticated users)
Route::middleware(['auth', App\Http\Middleware\EnsureCheckoutAccess::class])->group(function () {
    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout');
    Route::post('/checkout/session', [CheckoutController::class, 'createSession'])->name('checkout.session');
    Route::get('/checkout/show', [CheckoutController::class, 'show'])->name('checkout.show');
});

// Subscription routes (authenticated users)
Route::middleware(['auth'])->group(function () {
    Route::get('/subscriptions', [App\Http\Controllers\SubscriptionController::class, 'index'])->name('subscriptions');
    Route::get('/products/{product}/subscription-plans', [App\Http\Controllers\SubscriptionController::class, 'plans'])->name('subscription.plans');
    Route::post('/subscription/checkout', [App\Http\Controllers\SubscriptionController::class, 'createCheckoutSession'])->name('subscription.checkout');
    Route::post('/subscription/create', [App\Http\Controllers\SubscriptionController::class, 'create'])->name('subscription.create');
    Route::post('/subscription/cancel', [App\Http\Controllers\SubscriptionController::class, 'cancel'])->name('subscription.cancel');
    Route::post('/subscription/resume', [App\Http\Controllers\SubscriptionController::class, 'resume'])->name('subscription.resume');
    Route::post('/subscription/change-plan', [App\Http\Controllers\SubscriptionController::class, 'changePlan'])->name('subscription.change-plan');
});

// Checkout result pages (authenticated but no cart validation needed)
Route::middleware(['auth'])->group(function () {
    Route::get('/checkout/success', [CheckoutController::class, 'success'])->name('checkout.success');
    Route::get('/checkout/cancel', [CheckoutController::class, 'cancel'])->name('checkout.cancel');
    Route::get('/subscription/success', [App\Http\Controllers\SubscriptionController::class, 'success'])->name('subscription.success');
    Route::get('/subscription/cancel', [App\Http\Controllers\SubscriptionController::class, 'cancelCheckout'])->name('subscription.cancel');
});

// Guest checkout (outside auth middleware)
Route::get('/checkout/guest', [CheckoutController::class, 'guestIndex'])->name('checkout.guest');
Route::post('/checkout/guest/session', [CheckoutController::class, 'guestCheckout'])->name('checkout.guest.session');

// Stripe webhooks (outside auth middleware - no CSRF protection needed)
Route::post('/stripe/webhook', [CheckoutController::class, 'webhook'])
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class])
    ->name('stripe.webhook');

Route::get('/about', function () {
    return Inertia::render('about');
})->name('about');

Route::get('/contact', function () {
    return Inertia::render('contact');
})->name('contact');

// API routes for AJAX calls
Route::prefix('api')->group(function () {
    Route::post('/promotion/validate', [App\Http\Controllers\Api\PromotionController::class, 'validate'])->name('api.promotion.validate');
});

Route::get('/api/translations/{locale}', function ($locale) {
    $supportedLocales = ['en', 'es', 'fr', 'de', 'it'];
    
    if (!in_array($locale, $supportedLocales)) {
        $locale = 'en'; // fallback to English
    }
    
    $translationFile = base_path("lang/{$locale}.json");
    
    if (file_exists($translationFile)) {
        $translations = json_decode(file_get_contents($translationFile), true);
        return response()->json($translations);
    }
    
    return response()->json(['error' => 'Translations not found'], 404);
})->name('translations');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
