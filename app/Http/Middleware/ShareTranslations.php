<?php

namespace App\Http\Middleware;

use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
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
     * Get translations for the specified locale, with English for any key it lacks
     */
    private function getTranslations(string $locale): array
    {
        return Locales::translations(Locales::isSupported($locale) ? $locale : 'en');
    }
}
