<?php

namespace Tests\Feature;

use App\Services\OrderCreationService;
use App\Support\Locales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LocalesTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_supported_locale_has_a_valid_translation_file_and_no_file_is_orphaned(): void
    {
        foreach (Locales::supported() as $code) {
            $path = lang_path($code.'.json');

            $this->assertFileExists($path, "lang/{$code}.json is missing");
            $this->assertIsArray(json_decode(file_get_contents($path), true), "lang/{$code}.json is not valid JSON");
        }

        $files = collect(glob(lang_path('*.json')))->map(fn ($f) => basename($f, '.json'))->sort()->values()->all();
        $supported = collect(Locales::supported())->sort()->values()->all();

        $this->assertSame($supported, $files, 'lang/*.json and config(app.supported_locales) must list the same locales');
    }

    public function test_helper_validates_and_falls_back(): void
    {
        config(['app.default_locale' => 'it']);

        $this->assertTrue(Locales::isSupported('de'));
        $this->assertFalse(Locales::isSupported('xx'));
        $this->assertFalse(Locales::isSupported(null));
        $this->assertFalse(Locales::isSupported(['de']));
        $this->assertFalse(Locales::isSupported('DE'));
        $this->assertSame('it', Locales::fallback());
    }

    public function test_the_order_service_alias_lists_the_same_locales_as_the_config(): void
    {
        $this->assertEqualsCanonicalizing(Locales::supported(), OrderCreationService::SUPPORTED_LOCALES);
    }

    public function test_the_frontend_static_fallback_and_header_agree_with_the_config(): void
    {
        $context = file_get_contents(resource_path('js/contexts/TranslationContext.tsx'));
        preg_match('/DEFAULT_SUPPORTED_LOCALES\s*=\s*\[([^\]]*)\]/', $context, $m);
        $frontend = array_map(fn ($c) => trim($c, " '\""), explode(',', $m[1] ?? ''));

        $this->assertSame(Locales::supported(), $frontend, 'DEFAULT_SUPPORTED_LOCALES in TranslationContext.tsx is out of date');
        $this->assertDoesNotMatchRegularExpression('/<SelectItem value="(en|es|fr|de|it)"/', file_get_contents(resource_path('js/components/header.tsx')));
    }

    public function test_the_locale_list_is_shared_with_the_frontend(): void
    {
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('supportedLocales', Locales::supported()));
    }

    public function test_language_switching_only_accepts_supported_locales(): void
    {
        $this->post('/change-language', ['locale' => 'de'])->assertRedirect();
        $this->assertSame('de', session('locale'));

        $this->post('/change-language', ['locale' => 'xx'])->assertRedirect();
        $this->assertNotSame('xx', session('locale'));

        $response = $this->getJson('/api/translations/xx')->assertOk();
        // assertTrue on purpose: diffing two translation files with assertSame/assertExactJson exhausts memory
        $this->assertTrue($response->json() === Locales::translations(Locales::fallback()));
        // the session's German must not leak into the fallback
        $this->assertSame(config('app.default_locale'), Locales::fallback());
    }

    /**
     * Every translation key as a dotted path ("cart.coupon_applied"); flat keys ("settings.tax.rate.label") stay as they are.
     *
     * @return array<int, string>
     */
    private function flatKeys(array $strings, string $prefix = ''): array
    {
        $keys = [];

        foreach ($strings as $key => $value) {
            $keys = is_array($value)
                ? array_merge($keys, $this->flatKeys($value, $prefix.$key.'.'))
                : array_merge($keys, [$prefix.$key]);
        }

        return $keys;
    }

    public function test_every_locale_has_every_english_key(): void
    {
        $english = $this->flatKeys(json_decode(file_get_contents(lang_path('en.json')), true));
        $missing = [];

        foreach (Locales::supported() as $locale) {
            if ($locale === 'en') {
                continue;
            }

            $keys = $this->flatKeys(json_decode(file_get_contents(lang_path("{$locale}.json")), true));

            foreach (array_diff($english, $keys) as $key) {
                $missing[] = "{$locale}: {$key}";
            }
        }

        $this->assertSame([], array_values($missing), 'keys missing from a locale (they would render as the raw key)');
    }

    public function test_a_key_missing_from_a_locale_is_served_in_english(): void
    {
        $dir = sys_get_temp_dir().'/lang-fallback-'.uniqid();
        mkdir($dir);
        file_put_contents("{$dir}/en.json", json_encode([
            'cart' => ['shipping' => 'Shipping', 'coupon_applied' => 'Coupon Applied'],
            'settings.tax.rate.label' => 'VAT rate',
            'welcome' => 'Welcome',
        ]));
        file_put_contents("{$dir}/de.json", json_encode([
            'cart' => ['shipping' => 'Versand'],
            'welcome' => 'Willkommen',
        ]));
        $this->app->useLangPath($dir);

        try {
            $response = $this->getJson('/api/translations/de')->assertOk();

            // the German texts win, the missing ones (nested and flat) come from English instead of the raw key
            $this->assertSame('Versand', $response->json('cart.shipping'));
            $this->assertSame('Willkommen', $response->json('welcome'));
            $this->assertSame('Coupon Applied', $response->json('cart.coupon_applied'));
            $this->assertSame('VAT rate', $response->json()['settings.tax.rate.label'] ?? null);
        } finally {
            array_map('unlink', glob("{$dir}/*.json"));
            rmdir($dir);
        }
    }
}
