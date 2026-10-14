<?php

/*
|--------------------------------------------------------------------------
| Guard rails for swappable carrier adapters
|--------------------------------------------------------------------------
|
| Swapping the carrier API must mean: a new folder in Drivers/, one registration line in
| TrackingServiceProvider, one env var. These tests fail the build when the core starts to know
| about a concrete adapter, or an adapter leaks into the rest of the application.
*/

use App\Shipping\Tracking\Contracts\TrackingProvider;
use App\Shipping\Tracking\Drivers\Spediamo\SpediamoResponseMapper;
use App\Shipping\Tracking\Drivers\Spediamo\SpediamoStatusMap;

const TRK_DRIVERS = 'App\Shipping\Tracking\Drivers';

// (ii) the core never depends on a concrete driver (one rule per target: array targets are not checked one by one)
foreach ([
    'App\Shipping\Tracking\ShipmentTrackingService',
    'App\Shipping\Tracking\Jobs',
    'App\Shipping\Tracking\Http',
    'App\Shipping\Tracking\Contracts',
    'App\Shipping\Tracking\DTO',
    'App\Shipping\Tracking\Enums',
    'App\Shipping\Tracking\Exceptions',
    'App\Shipping\Tracking\Support',
    'App\Shipping\Tracking\Auth',
    'App\Models\Order',
    'App\Console\Commands\SyncShipmentTracking',
] as $coreTarget) {
    arch("core {$coreTarget} does not depend on concrete drivers")
        ->expect($coreTarget)
        ->not->toUse(TRK_DRIVERS);
}

// Only the manager (built-in manual/null/fake creators) and the registering provider may name a driver outside Drivers/.
// A file scan on purpose: arch()'s toOnlyBeUsedIn() parses the whole application and exhausts the 128 MB default memory limit.
it('names concrete drivers only in the manager, the registering provider and the Drivers folder', function () {
    $root = dirname(__DIR__, 3);
    $allowed = ['app/Shipping/Tracking/TrackingManager.php', 'app/Providers/TrackingServiceProvider.php'];
    $offenders = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/app", FilesystemIterator::SKIP_DOTS)) as $file) {
        $relative = ltrim(substr($file->getPathname(), strlen($root)), '/');

        if (! $file->isFile() || ! str_ends_with($relative, '.php') || in_array($relative, $allowed, true)
            || str_starts_with($relative, 'app/Shipping/Tracking/Drivers/')) {
            continue;
        }

        if (str_contains((string) file_get_contents($file->getPathname()), 'Shipping\\Tracking\\Drivers')) {
            $offenders[] = $relative;
        }
    }

    expect($offenders)->toBe([]);
});

// (iii) every concrete driver is a TrackingProvider
arch('every driver implements TrackingProvider')
    ->expect(TRK_DRIVERS)
    ->toImplement(TrackingProvider::class)
    ->ignoring([
        'App\Shipping\Tracking\Drivers\Concerns',
        SpediamoResponseMapper::class,
        SpediamoStatusMap::class,
    ]);

// (iv) contracts are interfaces only
arch('contracts contain only interfaces')
    ->expect('App\Shipping\Tracking\Contracts')
    ->toBeInterfaces();

arch('shared driver helpers are traits')
    ->expect('App\Shipping\Tracking\Drivers\Concerns')
    ->toBeTraits();

// (i) the provider-specific name stays inside its adapter
it('mentions the Spediamo adapter only in its own folder, its registration, its config key, docs and tests', function () {
    $root = dirname(__DIR__, 3);

    // The one registration line and the config key. Everything else is scanned.
    $allowed = [
        'app/Providers/TrackingServiceProvider.php',
        'config/shipping.php',
    ];

    $offenders = [];

    foreach (['app', 'bootstrap', 'config', 'database', 'routes', 'resources/js', 'resources/views', 'resources/css'] as $dir) {
        if (! is_dir("{$root}/{$dir}")) {
            continue;
        }

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/{$dir}", FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            $relative = ltrim(substr($file->getPathname(), strlen($root)), '/');

            if (! $file->isFile() || ! preg_match('/\.(php|tsx?|jsx?|css|json)$/', $relative)
                || in_array($relative, $allowed, true)
                || str_starts_with($relative, 'app/Shipping/Tracking/Drivers/Spediamo/')) {
                continue;
            }

            if (stripos((string) file_get_contents($file->getPathname()), 'spediamo') !== false) {
                $offenders[] = $relative;
            }
        }
    }

    // Translations: "Spediamo" is also the Italian verb "we ship", so only look for the company, API or env names.
    foreach (glob("{$root}/lang/*.json") ?: [] as $file) {
        if (preg_match('/spediamo\.it|spediamo\s+(api|provider|adapter|tracking)|SPEDIAMO_/i', (string) file_get_contents($file))) {
            $offenders[] = 'lang/'.basename($file);
        }
    }

    expect($offenders)->toBe([], 'carrier-specific code must stay in app/Shipping/Tracking/Drivers/Spediamo');
});

it('keeps all adapter settings under shipping.tracking.drivers.spediamo and the registration to one line', function () {
    $root = dirname(__DIR__, 3);

    $provider = (string) file_get_contents("{$root}/app/Providers/TrackingServiceProvider.php");
    expect(substr_count($provider, "->extend('"))->toBe(1);

    // the adapter folder contains no other carrier's code and is self-contained: only core namespaces are imported
    foreach (glob("{$root}/app/Shipping/Tracking/Drivers/Spediamo/*.php") as $file) {
        preg_match_all('/^use ([^;]+);/m', (string) file_get_contents($file), $imports);

        foreach ($imports[1] as $import) {
            expect($import)->toMatch('/^(App\\\\Shipping\\\\Tracking\\\\|Illuminate\\\\|Carbon\\\\|Closure$|DateTimeZone$|Throwable$)/', basename($file)." imports {$import}");
        }
    }
});
