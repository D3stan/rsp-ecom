<?php

use App\Http\Controllers\Admin\OrdersController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\CategoriesAndSizesController;
use App\Http\Controllers\Admin\ReviewsController;
use App\Http\Controllers\Admin\NewsletterSubscribersController;
use App\Http\Controllers\Admin\SettingsController;
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
    Route::patch('orders/{order}/status', [OrdersController::class, 'updateStatus'])->name('orders.update-status');
    Route::patch('orders/{order}/cancel', [OrdersController::class, 'cancel'])->name('orders.cancel');
    Route::post('orders/{order}/refund', [OrdersController::class, 'refund'])->name('orders.refund');

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

    // TODO: Add other admin routes as they are implemented
    // Route::resource('customers', CustomersController::class);
    
});
