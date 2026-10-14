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
        config(['app.locale' => 'it']);

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
        $this->assertTrue($response->json() === json_decode(file_get_contents(lang_path(Locales::fallback().'.json')), true));
    }
}
