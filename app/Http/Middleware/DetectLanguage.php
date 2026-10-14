<?php

namespace App\Http\Middleware;

use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class DetectLanguage
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->determineLocale($request);
        
        // Set the application locale
        App::setLocale($locale);
        
        // Store in session for persistence
        Session::put('locale', $locale);
        
        return $next($request);
    }

    /**
     * Determine the appropriate locale for the user
     */
    private function determineLocale(Request $request): string
    {
        // 1. Check if locale is set in session (user preference)
        if (Session::has('locale') && Locales::isSupported(Session::get('locale'))) {
            return Session::get('locale');
        }

        // 2. Check for locale in query parameters (for language switching)
        if ($request->has('locale') && Locales::isSupported($request->get('locale'))) {
            return $request->get('locale');
        }

        // 3. Detect from browser's Accept-Language header
        $browserLocale = $this->detectBrowserLanguage($request);
        if ($browserLocale && Locales::isSupported($browserLocale)) {
            return $browserLocale;
        }

        // 4. Fallback to default locale
        return Locales::fallback();
    }

    /**
     * Detect language from browser's Accept-Language header
     */
    private function detectBrowserLanguage(Request $request): ?string
    {
        // Best match against your supported list (handles q-values, regions, spacing, etc.)
        $supported = Locales::supported();
        $best = $request->getPreferredLanguage($supported);
        if ($best) {
            return str_replace('_', '-', strtolower($best)); // normalize
        }

        // Fallback: walk full list, try exact then primary subtags
        foreach ($request->getLanguages() as $tag) {        // already sorted by q
            $tag = str_replace('_', '-', strtolower($tag));
            if (in_array($tag, $supported, true)) return $tag;

            $primary = explode('-', $tag, 2)[0];
            if (in_array($primary, $supported, true)) return $primary;
        }
        
        return null;
    }
}
