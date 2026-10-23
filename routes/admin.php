<?php

use App\Http\Controllers\Admin\CustomersController;
use App\Http\Controllers\Admin\OrdersController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\CategoriesAndSizesController;
use App\Http\Controllers\Admin\ReviewMediaController;
use App\Http\Controllers\Admin\ReviewsController;
use App\Http\Controllers\Admin\NewsletterSubscribersController;
use App\Http\Controllers\Admin\NewsletterCampaignsController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\ShippingQuotesController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    
    // Admin Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Orders Management
    // Must be registered before the resource: otherwise PATCH orders/{order} swallows "orders/bulk-status".
    Route::patch('orders/bulk-status', [OrdersController::class, 'bulkUpdateStatus'])->name('orders.bulk-update-status');
    Route::resource('orders', OrdersController::class)->only(['index', 'show', 'edit', 'update']);
    Route::get('orders/{order}/ship', [OrdersController::class, 'showShip'])->name('orders.show-ship');
    Route::post('orders/{order}/ship', [OrdersController::class, 'processShip'])->name('orders.process-ship');
    Route::patch('orders/{order}/shipping-address', [OrdersController::class, 'updateShippingAddress'])->name('orders.update-shipping-address');
    Route::post('orders/{order}/quotes', [ShippingQuotesController::class, 'quoteOrder'])->name('orders.quotes');
    // Optional label layer: stream a shipment's label PDF, cancel a shipment at the carrier
    Route::get('orders/{order}/shipments/{shipment}/label', [OrdersController::class, 'downloadLabel'])->scopeBindings()->name('orders.shipments.label');
    Route::post('orders/{order}/shipments/{shipment}/complete', [OrdersController::class, 'completeShipment'])->scopeBindings()->name('orders.shipments.complete');
    Route::delete('orders/{order}/shipments/{shipment}', [OrdersController::class, 'cancelShipment'])->scopeBindings()->name('orders.shipments.cancel');
    Route::patch('orders/{order}/status', [OrdersController::class, 'updateStatus'])->name('orders.update-status');
    Route::patch('orders/{order}/cancel', [OrdersController::class, 'cancel'])->name('orders.cancel');
    Route::post('orders/{order}/refund', [OrdersController::class, 'refund'])->name('orders.refund');
    Route::post('orders/{order}/refunds/{refund}/retry', [OrdersController::class, 'retryRefund'])->scopeBindings()->name('orders.refunds.retry');

    // Shipping Quotes
    Route::get('shipping/quotes', [ShippingQuotesController::class, 'index'])->name('shipping.quotes');
    Route::post('shipping/quotes/calculate', [ShippingQuotesController::class, 'quote'])->name('shipping.quotes.calculate');

    // Products Management
    Route::resource('products', ProductController::class);
    Route::patch('products/{product}/quick-stock', [ProductController::class, 'quickStock'])->name('products.quick-stock');
    Route::delete('products/{product}/images/{filename}', [ProductController::class, 'removeImage'])->name('products.remove-image');
    Route::post('products/{product}/reviews', [ProductController::class, 'storeReview'])->name('products.reviews.store');

    // Categories & Sizes Management
    Route::get('/categories-sizes', [CategoriesAndSizesController::class, 'index'])->name('categories-sizes');
    
    // Category routes
    Route::post('/categories', [CategoriesAndSizesController::class, 'storeCategory'])->name('categories.store');
    Route::patch('/categories/{category}', [CategoriesAndSizesController::class, 'updateCategory'])->name('categories.update');
    Route::delete('/categories/{category}', [CategoriesAndSizesController::class, 'destroyCategory'])->name('categories.destroy');
    
    // Size routes
    Route::post('/sizes', [CategoriesAndSizesController::class, 'storeSize'])->name('sizes.store');
    Route::patch('/sizes/{size}', [CategoriesAndSizesController::class, 'updateSize'])->name('sizes.update');
    Route::delete('/sizes/{size}', [CategoriesAndSizesController::class, 'destroySize'])->name('sizes.destroy');

    // Reviews Management
    Route::patch('reviews/bulk-update', [ReviewsController::class, 'bulkUpdate'])->name('reviews.bulk-update');
    Route::patch('reviews/{review}/approve', [ReviewsController::class, 'approve'])->name('reviews.approve');
    Route::patch('reviews/{review}/reject', [ReviewsController::class, 'reject'])->name('reviews.reject');
    Route::patch('reviews/{review}/approve-with-media', [ReviewsController::class, 'approveWithMedia'])->name('reviews.approve-with-media');
    Route::scopeBindings()->group(function () {
        Route::patch('reviews/{review}/media/{media}/approve', [ReviewMediaController::class, 'approve'])->name('reviews.media.approve');
        Route::patch('reviews/{review}/media/{media}/reject', [ReviewMediaController::class, 'reject'])->name('reviews.media.reject');
        Route::patch('reviews/{review}/media/{media}/feature', [ReviewMediaController::class, 'feature'])->name('reviews.media.feature');
    });
    Route::resource('reviews', ReviewsController::class)->only(['index', 'show', 'update', 'destroy']);

    // Settings Management (registry-driven, docs/admin-settings.md)
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
    Route::get('/settings/{group}/history', [SettingsController::class, 'history'])->name('settings.history');
    Route::patch('/settings/{group}', [SettingsController::class, 'update'])->name('settings.update');
    Route::delete('/settings/{key}', [SettingsController::class, 'reset'])->name('settings.reset');

    // Newsletter (docs/newsletter-and-reminders.md)
    Route::redirect('/newsletter', '/admin/newsletter/subscribers');
    Route::get('/newsletter/subscribers', [NewsletterSubscribersController::class, 'index'])->name('newsletter.subscribers');
    Route::get('/newsletter/subscribers/export', [NewsletterSubscribersController::class, 'export'])->name('newsletter.subscribers.export');
    Route::post('/newsletter/subscribers/{subscriber}/unsubscribe', [NewsletterSubscribersController::class, 'unsubscribe'])->name('newsletter.subscribers.unsubscribe');
    Route::delete('/newsletter/subscribers/{subscriber}', [NewsletterSubscribersController::class, 'destroy'])->name('newsletter.subscribers.destroy');
    // Before the resource, so "preview" is not taken for a campaign id
    Route::post('/newsletter/campaigns/preview', [NewsletterCampaignsController::class, 'preview'])->name('newsletter.campaigns.preview');
    Route::resource('newsletter/campaigns', NewsletterCampaignsController::class)
        ->only(['index', 'create', 'store', 'show', 'edit', 'update'])
        ->parameters(['campaigns' => 'campaign'])
        ->names('newsletter.campaigns');
    Route::post('/newsletter/campaigns/{campaign}/test', [NewsletterCampaignsController::class, 'test'])->middleware('throttle:10,1')->name('newsletter.campaigns.test');
    Route::post('/newsletter/campaigns/{campaign}/send', [NewsletterCampaignsController::class, 'send'])->name('newsletter.campaigns.send');
    Route::post('/newsletter/campaigns/{campaign}/schedule', [NewsletterCampaignsController::class, 'schedule'])->name('newsletter.campaigns.schedule');
    Route::post('/newsletter/campaigns/{campaign}/cancel', [NewsletterCampaignsController::class, 'cancel'])->name('newsletter.campaigns.cancel');

    // Customers
    Route::resource('customers', CustomersController::class)->only(['index', 'show', 'update']);
    Route::patch('customers/{customer}/active', [CustomersController::class, 'toggleActive'])->name('customers.toggle-active');
    Route::post('customers/{customer}/reset-link', [CustomersController::class, 'sendResetLink'])->middleware('throttle:6,1')->name('customers.reset-link');
    
});
