<?php

use App\Http\Controllers\Settings\AddressController;
use App\Http\Controllers\Settings\NewsletterPreferenceController;
use App\Http\Controllers\Settings\NotificationPreferenceController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::patch('settings/notifications', [NotificationPreferenceController::class, 'update'])->name('notification-preferences.update');

    Route::put('settings/newsletter', [NewsletterPreferenceController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('newsletter.preference');

    Route::get('settings/addresses', [AddressController::class, 'index'])->name('addresses.index');
    Route::post('settings/addresses', [AddressController::class, 'store'])->name('addresses.store');
    Route::put('settings/addresses/{address}', [AddressController::class, 'update'])->whereNumber('address')->name('addresses.update');
    Route::delete('settings/addresses/{address}', [AddressController::class, 'destroy'])->whereNumber('address')->name('addresses.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');

    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('password.update');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/appearance');
    })->name('appearance');
});
