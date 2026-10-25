<?php

namespace App\Providers;

use App\Console\Commands\ClearSitemaps;
use App\Console\Commands\GenerateSitemap;
use App\Console\Commands\GenerateSitemapIndex;
use App\Console\Commands\SendTestOrderConfirmation;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Observers\OrderObserver;
use App\Observers\ProductVariantObserver;
use App\Services\StripeService;
use App\Shipping\Labels\LabelManager;
use App\Shipping\Tracking\Contracts\TrackingProvider;
use App\Shipping\Tracking\TrackingManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;
use Stripe\Stripe;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Cashier's own routes (GET stripe/payment/{id}, POST stripe/webhook) are not registered: the payment page is
        // unused and the webhook is re-declared explicitly in routes/web.php (named cashier.webhook).
        Cashier::ignoreRoutes();

        // Carrier tracking module: one manager, the active driver is injectable as TrackingProvider
        $this->app->singleton(TrackingManager::class);

        // Label layer: one manager; a LabelProvider is resolved only when label creation is on (ShippingService).
        $this->app->singleton(LabelManager::class);
        $this->app->bind(TrackingProvider::class, fn ($app) => $app->make(TrackingManager::class)->driver());

        // Register console commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                SendTestOrderConfirmation::class,
                GenerateSitemap::class,
                GenerateSitemapIndex::class,
                ClearSitemaps::class,
            ]);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force HTTPS when APP_URL is HTTPS
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');

        }

        // Initialize Stripe API key globally (only if settings table exists)
        try {
            if (Schema::hasTable('settings')) {
                $stripeSecret = StripeService::getSecretKey();
                if ($stripeSecret) {
                    Stripe::setApiKey($stripeSecret);
                }
            }
        } catch (\Exception $e) {
            // Silently skip Stripe initialization when database is unavailable
        }

        $this->warnAboutUnsafeProductionMail();

        // Lifecycle emails (delivered, cancelled) follow order status changes
        Order::observe(OrderObserver::class);

        // Stock that comes back (any Eloquent write to a variant) is announced for back-in-stock emails
        ProductVariant::observe(ProductVariantObserver::class);

        // Configure Cashier
        // Cashier::calculateTaxes(); // Uncomment to enable automatic tax calculation

        // Set currency (can also be done via CASHIER_CURRENCY env variable)
        // Cashier::useCurrency('usd', '$');

        // Configure custom models if needed
        // Cashier::useCustomerModel(User::class);
    }

    /**
     * Customer mail silently goes nowhere with the log/array transport (or with no real
     * sender/support address). Make that impossible to miss in production logs.
     * Throttled to once an hour so the per-minute scheduler boot doesn't flood the log.
     */
    private function warnAboutUnsafeProductionMail(): void
    {
        if (! $this->app->isProduction()) {
            return;
        }

        $problems = [];

        if (in_array(config('mail.default'), ['log', 'array'], true)) {
            $problems[] = 'MAIL_MAILER is "'.config('mail.default').'": no email is delivered to customers';
        }

        // Placeholder domains: example.com (code defaults) and your-domain (.env.example)
        $isPlaceholder = fn (string $address): bool => str_ends_with($address, '@example.com') || str_ends_with($address, '@your-domain');

        if ($isPlaceholder((string) config('mail.from.address'))) {
            $problems[] = 'MAIL_FROM_ADDRESS is still a placeholder address';
        }

        // This provider boots before SettingsServiceProvider has overlaid contact.support_email onto
        // mail.support_address, so read the effective value (admin-customised or the env default) from the settings.
        if ($isPlaceholder($this->effectiveSupportAddress())) {
            $problems[] = 'MAIL_SUPPORT_ADDRESS is not set (emails would show a placeholder support address)';
        }

        if ($problems === []) {
            return;
        }

        try {
            if (! Cache::add('mail-config-warning', true, now()->addHour())) {
                return;
            }
        } catch (\Throwable) {
            // Cache unavailable (e.g. before migrations): warn anyway.
        }

        Log::critical('Unsafe production mail configuration: '.implode('; ', $problems));
    }

    /**
     * The support address emails will use: the admin-customised setting, else config('mail.support_address').
     */
    private function effectiveSupportAddress(): string
    {
        try {
            return (string) settings('contact.support_email', config('mail.support_address'));
        } catch (\Throwable) {
            return (string) config('mail.support_address');
        }
    }
}
