<?php

use App\Http\Controllers\Admin\OrdersController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ProductController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    
    // Admin Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Orders Management
    Route::resource('orders', OrdersController::class)->only(['index', 'show', 'update']);
    Route::patch('orders/{order}/status', [OrdersController::class, 'updateStatus'])->name('orders.update-status');
    Route::patch('orders/bulk-status', [OrdersController::class, 'bulkUpdateStatus'])->name('orders.bulk-update-status');
    Route::patch('orders/{order}/cancel', [OrdersController::class, 'cancel'])->name('orders.cancel');
    Route::post('orders/{order}/refund', [OrdersController::class, 'refund'])->name('orders.refund');

    // Products Management
    Route::resource('products', ProductController::class);
    Route::patch('products/{product}/quick-stock', [ProductController::class, 'quickStock'])->name('products.quick-stock');
    Route::delete('products/{product}/images/{filename}', [ProductController::class, 'removeImage'])->name('products.remove-image');
    Route::post('products/{product}/reviews', [ProductController::class, 'storeReview'])->name('products.reviews.store');

    // TODO: Add other admin routes as they are implemented
    // Route::resource('customers', CustomersController::class);
    // Route::resource('categories', CategoriesController::class);
    // Route::resource('reviews', ReviewsController::class);
    
});
