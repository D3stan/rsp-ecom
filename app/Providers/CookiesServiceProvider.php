<?php

namespace App\Providers;

use Whitecube\LaravelCookieConsent\CookiesServiceProvider as ServiceProvider;
use Whitecube\LaravelCookieConsent\Facades\Cookies;

class CookiesServiceProvider extends ServiceProvider
{
    /**
     * Define the cookies users should be aware of.
     */
    protected function registerCookies(): void
    {
        // Register Laravel's base cookies under the "required" cookies section:
        Cookies::essentials()
            ->session()
            ->csrf();

        // Register all Analytics cookies at once using one single shorthand method:
        // Cookies::analytics()
        //    ->google(
        //         id: config('cookieconsent.google_analytics.id'),
        //         anonymizeIp: config('cookieconsent.google_analytics.anonymize_ip')
        //    );

        // Sentry Session Replay: only records the moments around an error, with all text and inputs masked, and is
        // only started once the visitor accepts this category (see the `sentry` shared prop and resources/js/lib/sentry.ts).
        Cookies::analytics()
            ->name('sentry_replay')
            ->description('Records a masked replay of the moments around an error, so we can fix bugs we would otherwise never hear about.')
            ->duration(0);

        // Register custom cookies under the pre-existing "optional" category:
        Cookies::optional()
            ->name('darkmode_enabled')
            ->description('This cookie helps us remember your preferences regarding the interface\'s brightness.')
            ->duration(120)
            ->accepted(fn (Consent $consent, MyDarkmode $darkmode) => $consent->cookie(value: $darkmode->getDefaultValue()));
    }
}
