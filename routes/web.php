<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProductsController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\PageController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/products', [ProductsController::class, 'index'])->name('products');
Route::get('/products/{slug}', [ProductsController::class, 'show'])->name('products.show');

// Category routes
Route::get('/categories/{slug}', [CategoryController::class, 'show'])->name('categories.show');

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

// Cashier-based Checkout routes (authenticated users)
Route::middleware(['auth', 'verified'])->group(function () {
    // Product checkout using Cashier methods
    Route::get('/checkout/product/{priceId}', [CheckoutController::class, 'productCheckout'])->name('checkout.product');
    Route::get('/checkout/product/{priceId}/promo', [CheckoutController::class, 'productCheckoutWithPromo'])->name('checkout.product.promo');
    Route::get('/checkout/product/{priceId}/tax', [CheckoutController::class, 'productCheckoutWithTax'])->name('checkout.product.tax');
    
    // Single charge checkout
    Route::get('/checkout/charge/{amount}/{name}', [CheckoutController::class, 'singleChargeCheckout'])->name('checkout.charge');
});

// Generic checkout route - redirects to checkout details page
Route::get('/checkout', function (Request $request) {
    if ($request->user()) {
        return redirect()->route('checkout.details');
    } else {
        return redirect()->route('guest.checkout.details');
    }
})->name('checkout');

// Checkout details pages (new intermediate step)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/checkout/details', [CheckoutController::class, 'showDetails'])->name('checkout.details');
    Route::get('/checkout/show', [CheckoutController::class, 'show'])->name('checkout.show');
});

Route::get('/guest/checkout/details', [CheckoutController::class, 'showGuestDetails'])->name('guest.checkout.details');

// Add missing guest checkout route that redirects to details
Route::get('/guest/checkout', function (Request $request) {
    return redirect()->route('guest.checkout.details');
})->name('guest.checkout.redirect');

// Cart-based checkout routes (bridges cart workflow with Cashier)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/checkout/cart', [CheckoutController::class, 'cartCheckout'])->name('checkout.cart');
    Route::post('/checkout/session', [CheckoutController::class, 'createSession'])->name('checkout.session');
});

// Guest cart checkout
Route::post('/guest/checkout/cart', [CheckoutController::class, 'guestCartCheckout'])->name('guest.cart.checkout');
Route::post('/guest/checkout/session', [CheckoutController::class, 'createGuestSession'])->name('checkout.guest.session');

// Success and cancel pages - accessible to both authenticated and guest users
Route::get('/checkout/success', [CheckoutController::class, 'success'])->name('checkout.success');
Route::get('/checkout/cancel', [CheckoutController::class, 'cancel'])->name('checkout.cancel');

// Remove test route since it was causing Inertia issues

// Individual product checkout routes using Cashier
Route::get('/guest/checkout/{priceId}', [CheckoutController::class, 'guestCheckout'])->name('guest.checkout');
Route::get('/guest/checkout/{priceId}/promo/{promoCode}', [CheckoutController::class, 'guestCheckoutWithPromo'])->name('guest.checkout.promo');

// Stripe webhook (Cashier handles this automatically)
// Configure in Stripe Dashboard to point to: /stripe/webhook
// Cashier registers this route automatically and uses our custom webhook controller

Route::get('/about', [PageController::class, 'about'])->name('about');

Route::get('/contact', [App\Http\Controllers\ContactController::class, 'index'])->name('contact');
Route::post('/contact', [App\Http\Controllers\ContactController::class, 'store'])->name('contact.store');

Route::get('/privacy', [PageController::class, 'privacy'])->name('privacy');

Route::get('/shipping-returns', [PageController::class, 'shippingReturns'])->name('shipping-returns');

Route::get('/terms', [PageController::class, 'terms'])->name('terms');

Route::get('/faq', [PageController::class, 'faq'])->name('faq');

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

// Language switching route
Route::post('/change-language', function (Request $request) {
    $locale = $request->input('locale');
    $supportedLocales = ['en', 'es', 'fr', 'de', 'it'];
    
    if (in_array($locale, $supportedLocales)) {
        Session::put('locale', $locale);
        App::setLocale($locale);
    }
    
    return back();
})->name('change-language');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        // Set noindex for user dashboard
        \Artesaos\SEOTools\Facades\SEOMeta::addMeta('robots', 'noindex,nofollow', 'name');
        \Artesaos\SEOTools\Facades\SEOMeta::setTitle('Dashboard – ' . config('app.name'));
        
        // Redirect admin users to admin dashboard
        if (auth()->user()->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }
        
        $user = auth()->user();
        
        // Get recent orders with items and products
        $recentOrders = $user->orders()
            ->with(['orderItems.product', 'orderItems.size'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($order) {
                $orderData = $order->toArray();
                $orderData['total'] = $order->total_amount; // Add alias for backward compatibility
                $orderData['order_items'] = $order->orderItems->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'order_id' => $item->order_id,
                        'product_id' => $item->product_id,
                        'product_name' => $item->product_name,
                        'quantity' => $item->quantity,
                        'price' => (float) $item->price,
                        'total' => (float) $item->total,
                        'product' => $item->product ? [
                            'id' => $item->product->id,
                            'name' => $item->product->name,
                            'slug' => $item->product->slug,
                            'image_url' => $item->product->image_url,
                        ] : null,
                        'size' => $item->size,
                    ];
                })->toArray();
                
                return $orderData;
            });
        
        // Calculate order statistics
        $orderStats = [
            'total' => $user->orders()->count(),
            'pending' => $user->orders()->where('status', 'pending')->count(),
            'completed' => $user->orders()->whereIn('status', ['completed', 'delivered'])->count(),
        ];
        
        // Get wishlist count and items
        $wishlistItems = $user->wishlist()
            ->with('product')
            ->limit(5)
            ->get()
            ->map(function ($wishlistItem) {
                return [
                    'id' => $wishlistItem->id,
                    'product' => [
                        'id' => $wishlistItem->product->id,
                        'name' => $wishlistItem->product->name,
                        'slug' => $wishlistItem->product->slug,
                        'price' => (float) $wishlistItem->product->price,
                        'image' => $wishlistItem->product->image_url,
                        'rating' => $wishlistItem->product->rating ?? null,
                    ],
                ];
            });
        
        $wishlistCount = $user->wishlist()->count();
        
        return Inertia::render('Dashboard/Dashboard', [
            'recentOrders' => $recentOrders,
            'orderStats' => $orderStats,
            'wishlistItems' => $wishlistItems,
            'wishlistCount' => $wishlistCount,
        ]);
    })->name('dashboard');
    Route::get('dashboard/orders', [DashboardController::class, 'orders'])->name('orders.index');
    Route::get('dashboard/wishlist', [DashboardController::class, 'wishlist'])->name('wishlist.index');
    
    // Review routes
    Route::get('reviews/create/{product}', [App\Http\Controllers\ReviewController::class, 'create'])->name('reviews.create');
    Route::post('reviews', [App\Http\Controllers\ReviewController::class, 'store'])->name('reviews.store');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/admin.php';

Route::get('/robots.txt', function () {
    $content = "User-agent: *
Disallow: /admin/
Disallow: /dashboard/
Disallow: /cart/
Disallow: /checkout/
Disallow: /api/

# Allow all other pages
Allow: /

# Sitemap location
Sitemap: " . url('/sitemap.xml');
    
    return response($content)
        ->header('Content-Type', 'text/plain');
})->name('robots');

// Fallback route for 404 errors - must be last
Route::fallback(function () {
    return Inertia::render('Errors/NotFound');
});