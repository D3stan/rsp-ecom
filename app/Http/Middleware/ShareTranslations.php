<?php

namespace App\Http\Middleware;

use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;
use Symfony\Component\HttpFoundation\Response;

class ShareTranslations extends Middleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
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
            Inertia::share('translations', $translations);
            Inertia::share('locale', $locale);
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
