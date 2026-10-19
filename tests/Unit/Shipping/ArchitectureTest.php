<?php

/*
|--------------------------------------------------------------------------
| Guard rails for coexisting modular shipping providers
|--------------------------------------------------------------------------
|
| Adding or removing an external carrier provider must mean: a single self-contained folder in
| app/Shipping/Providers/<Provider>/ and one manifest entry in config/shipping.php.
| These tests fail the build when:
|  - core shipping services, models, or commands depend on concrete drivers or providers;
|  - Labels and Tracking import from each other instead of shared App\Shipping\{Auth,Support,Contracts};
|  - a provider folder imports from another provider folder;
|  - a provider name leaks outside its own folder and config/shipping.php.
*/

use App\Shipping\Contracts\ShippingProviderManifest;
use App\Shipping\Tracking\Contracts\TrackingProvider;

const TRK_DRIVERS = 'App\Shipping\Tracking\Drivers';
const LBL_DRIVERS = 'App\Shipping\Labels\Drivers';
const EXT_PROVIDERS = 'App\Shipping\Providers';

// (i) the core never depends on concrete drivers or external providers
foreach ([
    'App\Shipping\ShippingService',
    'App\Shipping\ShipInput',
    'App\Shipping\Auth',
    'App\Shipping\Support',
    'App\Shipping\Contracts',
    'App\Shipping\Exceptions',
    'App\Shipping\Labels\ShipmentBuilder',
    'App\Shipping\Labels\Contracts',
    'App\Shipping\Labels\DTO',
    'App\Shipping\Labels\Exceptions',
    'App\Shipping\Tracking\ShipmentTrackingService',
    'App\Shipping\Tracking\Jobs',
    'App\Shipping\Tracking\Http',
    'App\Shipping\Tracking\Contracts',
    'App\Shipping\Tracking\DTO',
    'App\Shipping\Tracking\Enums',
    'App\Shipping\Tracking\Exceptions',
    'App\Shipping\Rates\RateManager',
    'App\Shipping\Rates\Contracts',
    'App\Shipping\Rates\DTO',
    'App\Shipping\Rates\Exceptions',
    'App\Models\Order',
    'App\Console\Commands\SyncShipmentTracking',
] as $coreTarget) {
    arch("core {$coreTarget} does not depend on concrete tracking drivers")
        ->expect($coreTarget)
        ->not->toUse(TRK_DRIVERS);

    arch("core {$coreTarget} does not depend on concrete label drivers")
        ->expect($coreTarget)
        ->not->toUse(LBL_DRIVERS);

    arch("core {$coreTarget} does not depend on external provider modules")
        ->expect($coreTarget)
        ->not->toUse(EXT_PROVIDERS);
}

// (ii) zero hidden dependencies between Labels and Tracking subsystems
arch('Labels subsystem never depends on Tracking subsystem')
    ->expect('App\Shipping\Labels')
    ->not->toUse('App\Shipping\Tracking');

arch('Tracking subsystem never depends on Labels subsystem')
    ->expect('App\Shipping\Tracking')
    ->not->toUse('App\Shipping\Labels');

arch('Rates subsystem never depends on Tracking subsystem')
    ->expect('App\Shipping\Rates')
    ->not->toUse('App\Shipping\Tracking');

arch('Rates subsystem never depends on Labels subsystem')
    ->expect('App\Shipping\Rates')
    ->not->toUse('App\Shipping\Labels');

arch('Labels subsystem never depends on Rates subsystem')
    ->expect('App\Shipping\Labels')
    ->not->toUse('App\Shipping\Rates');

arch('Tracking subsystem never depends on Rates subsystem')
    ->expect('App\Shipping\Tracking')
    ->not->toUse('App\Shipping\Rates');

// Only TrackingManager, built-in Drivers, and external Providers (extending AbstractHttpProvider) may reference Shipping\Tracking\Drivers.
it('names concrete tracking drivers only in TrackingManager, Drivers, and Providers', function () {
    $root = dirname(__DIR__, 3);
    $allowed = ['app/Shipping/Tracking/TrackingManager.php'];
    $offenders = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/app", FilesystemIterator::SKIP_DOTS)) as $file) {
        $relative = ltrim(substr($file->getPathname(), strlen($root)), '/');

        if (! $file->isFile() || ! str_ends_with($relative, '.php') || in_array($relative, $allowed, true)
            || str_starts_with($relative, 'app/Shipping/Tracking/Drivers/')
            || str_starts_with($relative, 'app/Shipping/Providers/')) {
            continue;
        }

        if (str_contains((string) file_get_contents($file->getPathname()), 'Shipping\\Tracking\\Drivers')) {
            $offenders[] = $relative;
        }
    }

    expect($offenders)->toBe([]);
});

// (iii) every built-in driver in App\Shipping\Tracking\Drivers is a TrackingProvider
arch('every built-in tracking driver implements TrackingProvider')
    ->expect(TRK_DRIVERS)
    ->toImplement(TrackingProvider::class)
    ->ignoring([
        'App\Shipping\Tracking\Drivers\Concerns',
    ]);

// (iv) contracts are interfaces only
arch('shipping contracts contain only interfaces')
    ->expect('App\Shipping\Contracts')
    ->toBeInterfaces();

arch('tracking contracts contain only interfaces')
    ->expect('App\Shipping\Tracking\Contracts')
    ->toBeInterfaces();

arch('label contracts contain only interfaces')
    ->expect('App\Shipping\Labels\Contracts')
    ->toBeInterfaces();

arch('rate contracts contain only interfaces')
    ->expect('App\Shipping\Rates\Contracts')
    ->toBeInterfaces();

arch('shared driver helpers are traits')
    ->expect('App\Shipping\Tracking\Drivers\Concerns')
    ->toBeTraits();

// (v) every external provider folder is self-contained, isolated from other providers, and never leaks into the app
it('keeps every provider in app/Shipping/Providers/<Provider> isolated and self-contained', function () {
    $root = dirname(__DIR__, 3);
    $providerDirs = glob("{$root}/app/Shipping/Providers/*", GLOB_ONLYDIR) ?: [];

    expect($providerDirs)->not->toBeEmpty();

    // Service providers must be manifest-driven and never hardcode a concrete provider name
    foreach (['app/Providers/TrackingServiceProvider.php', 'app/Providers/LabelServiceProvider.php', 'app/Providers/RateServiceProvider.php'] as $spFile) {
        $spContents = (string) file_get_contents("{$root}/{$spFile}");
        expect($spContents)->toContain('ShippingProviderManifest')
            ->and($spContents)->not->toContain('Providers\\');
    }

    foreach ($providerDirs as $dirPath) {
        $folderName = basename($dirPath);
        $providerSlug = strtolower($folderName);
        $manifestClass = "App\\Shipping\\Providers\\{$folderName}\\{$folderName}Manifest";

        expect(class_exists($manifestClass))->toBeTrue("{$manifestClass} must exist")
            ->and(is_subclass_of($manifestClass, ShippingProviderManifest::class))->toBeTrue();

        // 1. Every file inside the provider folder may only import allowed core/shipping namespaces and never another provider
        $providerFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dirPath, FilesystemIterator::SKIP_DOTS));
        foreach ($providerFiles as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            preg_match_all('/^use ([^;]+);/m', (string) file_get_contents($file->getPathname()), $imports);

            foreach ($imports[1] as $import) {
                $pattern = '/^(App\\\\Shipping\\\\Providers\\\\'.preg_quote($folderName, '/').'\\\\|App\\\\Shipping\\\\(Auth|Contracts|Exceptions|Support|Tracking|Labels|Rates)\\\\|Illuminate\\\\|Carbon\\\\|Closure$|DateTimeZone$|Throwable$)/';
                expect($import)->toMatch($pattern, $file->getBasename()." imports forbidden {$import}");
            }
        }

        // 2. No file outside the provider's own folder and config/shipping.php mentions the provider name
        $allowed = ['config/shipping.php'];
        $offenders = [];

        foreach (['app', 'bootstrap', 'config', 'database', 'routes', 'resources/js', 'resources/views', 'resources/css'] as $scanDir) {
            if (! is_dir("{$root}/{$scanDir}")) {
                continue;
            }

            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/{$scanDir}", FilesystemIterator::SKIP_DOTS));

            foreach ($files as $file) {
                $relative = ltrim(substr($file->getPathname(), strlen($root)), '/');

                if (! $file->isFile() || ! preg_match('/\.(php|tsx?|jsx?|css|json)$/', $relative)
                    || in_array($relative, $allowed, true)
                    || str_starts_with($relative, "app/Shipping/Providers/{$folderName}/")) {
                    continue;
                }

                if (preg_match('/\b'.preg_quote($providerSlug, '/').'\b/i', (string) file_get_contents($file->getPathname()))) {
                    $offenders[] = $relative;
                }
            }
        }

        // Translations: "Spediamo" is also the Italian verb "we ship", so only look for the company, API or env names.
        foreach (glob("{$root}/lang/*.json") ?: [] as $file) {
            $regex = '/'.preg_quote($providerSlug, '/').'\.it|'.preg_quote($providerSlug, '/').'\s+(api|provider|adapter|tracking)|'.strtoupper(preg_quote($providerSlug, '/')).'_/i';
            if (preg_match($regex, (string) file_get_contents($file))) {
                $offenders[] = 'lang/'.basename($file);
            }
        }

        expect($offenders)->toBe([], "Provider [{$folderName}] leaked outside app/Shipping/Providers/{$folderName}");
    }
});
