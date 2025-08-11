<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Inertia\Middleware;
use Symfony\Component\HttpFoundation\Response;

class ShareTranslations extends Middleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        
        // Only add translations to Inertia responses
        if ($response instanceof \Inertia\Response || 
            ($response instanceof \Illuminate\Http\Response && $response->headers->get('X-Inertia'))) {
            
            $locale = app()->getLocale();
            $translations = $this->getTranslations($locale);
            
            // Share translations with all Inertia responses
            \Inertia\Inertia::share('translations', $translations);
            \Inertia\Inertia::share('locale', $locale);
        }
        
        return $response;
    }
    
    /**
     * Get translations for the specified locale
     */
    private function getTranslations(string $locale): array
    {
        $translationPath = lang_path("{$locale}.json");
        
        if (File::exists($translationPath)) {
            $translations = json_decode(File::get($translationPath), true);
            return $translations ?: [];
        }
        
        // Fallback to English if locale file doesn't exist
        $fallbackPath = lang_path('en.json');
        if (File::exists($fallbackPath)) {
            $translations = json_decode(File::get($fallbackPath), true);
            return $translations ?: [];
        }
        
        return [];
    }
}
