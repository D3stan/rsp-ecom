<?php

use App\Http\Controllers\Auth\VerificationStatusController;
use Illuminate\Support\Facades\Route;

Route::prefix('verification')->group(function () {
    // API endpoint to check verification status (for auto-reload)
    Route::get('status', [VerificationStatusController::class, 'checkStatus'])
        ->middleware('throttle:200,1')
        ->name('api.verification.status');
});
