<?php

namespace App\Providers;

use Illuminate\Support\Facades\Auth;
use Whitecube\LaravelCookieConsent\CookiesServiceProvider as ServiceProvider;
use Whitecube\LaravelCookieConsent\Facades\Cookies;

class CookiesServiceProvider extends ServiceProvider
{
    /**
     * Define the cookies users should be aware of.
     *
     * Descriptions are translation keys, resolved per request in the banner and on the cookie policy page: this runs at
     * boot, before the visitor's locale is known.
     */
    protected function registerCookies(): void
    {
        // The consent cookie itself is registered by the package.
        Cookies::essentials()
            ->name(config('session.cookie'))
            ->description('cookieConsent::cookies.defaults.session')
            ->duration(config('session.lifetime'));

        Cookies::essentials()
            ->name('XSRF-TOKEN')
            ->description('cookieConsent::cookies.defaults.csrf')
            ->duration(config('session.lifetime'));

        // Only set when the visitor ticks "Remember me" at login; Laravel keeps it for 400 days.
        Cookies::essentials()
            ->name(Auth::guard()->getRecallerName())
            ->description('cookieConsent::cookies.defaults.remember')
            ->duration(576000);

        // Sentry Session Replay: only records the moments around an error, with all text and inputs masked, and is
        // only started once the visitor accepts this category (see the `sentry` shared prop and resources/js/lib/sentry.ts).
        // It lives in sessionStorage, not in a cookie.
        Cookies::analytics()
            ->name('sentry_replay')
            ->description('cookieConsent::cookies.defaults.sentry_replay')
            ->duration(0);

        // Interface preferences. The browser code (resources/js/lib/cookie-consent.ts) only writes them after the
        // visitor accepted this category, and clears them when the visitor withdraws.
        Cookies::optional()
            ->name('appearance')
            ->description('cookieConsent::cookies.defaults.appearance')
            ->duration(525600);

        Cookies::optional()
            ->name('sidebar_state')
            ->description('cookieConsent::cookies.defaults.sidebar_state')
            ->duration(10080);

        // Kept in localStorage, not in a cookie, until the visitor clears their browser data.
        Cookies::optional()
            ->name('locale')
            ->description('cookieConsent::cookies.defaults.locale')
            ->duration(525600);
    }
}
