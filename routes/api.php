<?php

use App\Http\Controllers\Auth\VerificationStatusController;
use App\Http\Controllers\WishlistController;
use Illuminate\Support\Facades\Route;

Route::prefix('verification')->group(function () {
    // API endpoint to check verification status (for auto-reload)
    Route::get('status', [VerificationStatusController::class, 'checkStatus'])
        ->middleware('throttle:200,1')
        ->name('api.verification.status');
});

// Wishlist API routes (authenticated users only)
Route::middleware(['auth', 'verified'])->prefix('wishlist')->group(function () {
    Route::post('/', [WishlistController::class, 'store'])->name('api.wishlist.store');
    Route::delete('/product', [WishlistController::class, 'destroyByProduct'])->name('api.wishlist.destroy-by-product');
    Route::post('/toggle', [WishlistController::class, 'toggle'])->name('api.wishlist.toggle');
    Route::post('/check', [WishlistController::class, 'check'])->name('api.wishlist.check');
    Route::get('/count', [WishlistController::class, 'count'])->name('api.wishlist.count');
});
