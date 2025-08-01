<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('home');
})->name('home');

Route::get('/products', function () {
    return Inertia::render('products');
})->name('products');

Route::get('/about', function () {
    return Inertia::render('about');
})->name('about');

Route::get('/contact', function () {
    return Inertia::render('contact');
})->name('contact');

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
