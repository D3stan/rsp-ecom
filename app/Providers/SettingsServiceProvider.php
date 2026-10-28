<?php

namespace App\Providers;

use App\Models\User;
use App\Settings\ConfigBridge;
use App\Settings\Events\SettingsChanged;
use App\Settings\Settings;
use App\Settings\SettingsRegistry;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * Admin configuration system (.ai/rules/settings.md): registry, service, config bridge and the
 * manage-settings gate.
 */
class SettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        require_once __DIR__.'/../Settings/helpers.php';

        $this->app->singleton(SettingsRegistry::class, fn () => SettingsRegistry::fromConfig());
        $this->app->singleton(Settings::class, fn ($app) => new Settings($app->make(SettingsRegistry::class)));
        $this->app->singleton(ConfigBridge::class, fn ($app) => new ConfigBridge($app->make(Settings::class), $app['config']));
    }

    public function boot(): void
    {
        Gate::define('manage-settings', fn (User $user) => $user->isAdmin());

        try {
            $bridge = $this->app->make(ConfigBridge::class);
        } catch (Throwable) {
            return;
        }

        $bridge->apply();

        // Long-running processes: pick up admin changes without a restart (cheap: one cache read when nothing changed)
        Event::listen([JobProcessing::class, Looping::class, ScheduledTaskStarting::class], fn () => $bridge->apply());
        Event::listen('Laravel\Octane\Events\RequestReceived', fn () => $bridge->apply());

        // Same process: a write is visible to config() immediately
        Event::listen(SettingsChanged::class, fn () => $bridge->apply(true));
    }
}
