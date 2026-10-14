<?php

use App\Providers\AppServiceProvider;
use App\Settings\Settings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * AppServiceProvider boots before SettingsServiceProvider overlays contact.support_email onto mail.support_address,
 * so the production mail warning must read the effective value from the settings, not from config().
 */
function runProductionMailCheck(): void
{
    app()->detectEnvironment(fn () => 'production');
    Cache::forget('mail-config-warning');

    config([
        'mail.default' => 'smtp',
        'mail.from.address' => 'shop@real-shop.test',
        // What config() still holds when AppServiceProvider boots: the env placeholder, not the setting
        'mail.support_address' => 'placeholder@example.com',
    ]);

    $method = new ReflectionMethod(AppServiceProvider::class, 'warnAboutUnsafeProductionMail');
    $method->invoke(new AppServiceProvider(app()));
}

afterEach(function () {
    app()->detectEnvironment(fn () => 'testing');
});

it('does not warn when the admin customised the support email', function () {
    app(Settings::class)->set('contact.support_email', 'help@real-shop.test');

    Log::shouldReceive('critical')->never();

    runProductionMailCheck();
});

it('warns when the effective support email is still a placeholder', function () {
    app(Settings::class)->set('contact.support_email', 'support@example.com');

    Log::shouldReceive('critical')
        ->once()
        ->withArgs(fn (string $message) => str_contains($message, 'MAIL_SUPPORT_ADDRESS is not set'));

    runProductionMailCheck();
});
