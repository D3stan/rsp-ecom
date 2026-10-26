<?php

namespace Tests\Feature;

use App\Settings\SettingDefinition;
use App\Settings\SettingsRegistry;
use App\Support\Locales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * The shipping / returns / VAT copy, in the five locales: EU-standard return policy (withdrawal days, delivery times
 * and the support address come from the settings, never from the text), no free-shipping promise anywhere (the only
 * free shipping is the admin threshold `shipping.free_threshold`, decided by App\Support\Shipping and shown by its own
 * keys), no hard-coded prices or currency symbols, and the same placeholders in every language.
 */
class PolicyCopyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Keys written for this policy => placeholders their text must contain (React `{x}`, PHP `:x`).
     */
    private const POLICY_KEYS = [
        'faq_items.shipping.answer_1' => ['{delivery}', '{dispatch}'],
        'faq_items.shipping.answer_2' => [],
        'faq_items.returns.answer_1' => ['{days}'],
        'faq_items.returns.answer_2' => [],
        'faq_items.returns.answer_3' => ['{days}'],
        'faq_items.returns.answer_4' => [],
        'about.return_policy' => ['{days}'],
        'checkout.success.step_2_desc' => ['{dispatch}'],
        'shipping_returns.shipping_destinations_text' => [],
        'shipping_returns.carriers_text' => [],
        'shipping_returns.standard_delivery' => ['{delivery}'],
        'shipping_returns.shipping_costs_text' => [],
        'shipping_returns.dispatch_text' => ['{delivery}', '{dispatch}'],
        'shipping_returns.no_free_returns' => ['{days}'],
        'shipping_returns.withdrawal_text' => ['{days}'],
        'shipping_returns.return_costs_intro' => [],
        'shipping_returns.no_change_mind' => [],
        'shipping_returns.return_steps' => [],
        'shipping_returns.step_1' => ['{days}'],
        'shipping_returns.step_2' => [],
        'shipping_returns.step_3' => [],
        'shipping_returns.step_4' => ['{days}'],
        'shipping_returns.return_shipping_text' => [],
        'shipping_returns.refund_text' => ['{days}'],
        'shipping_returns.withdrawal_form' => ['{email}'],
        'shipping_returns.important_label' => [],
        'shipping_returns.note_label' => [],
        'shipping_returns.contact_for_returns' => [],
        'emails.order_refunded.timing' => [],
        // VAT, minimum amount, guest checkout
        'checkout.vat_portion' => ['{rate}'],
        'emails.common.vat_portion' => [':rate'],
        'cart.min_amount_notice' => ['{amount}'],
        'checkout.min_amount_error' => [':amount'],
        'checkout.guest_checkout_disabled' => [],
    ];

    /** Keys that existed to promise free shipping / free returns / a rate table / express delivery. */
    private const REMOVED_KEYS = [
        'cart.free', 'checkout.free', 'checkout.success.free', 'guest_order.free', 'emails.common.shipping_free',
        'checkout.free_shipping_notice', 'checkout.return_policy_notice',
        'shipping_returns.express_delivery', 'shipping_returns.destination', 'shipping_returns.standard',
        'shipping_returns.express', 'shipping_returns.eu_countries', 'shipping_returns.uk',
    ];

    /**
     * The texts of the free-shipping threshold (setting `shipping.free_threshold`): shown only while the threshold is on
     * ("Free shipping" once reached, "Add X more for free shipping" below it) and in the admin settings page.
     */
    private const FREE_SHIPPING_THRESHOLD_KEYS = [
        'shipping.free', 'shipping.free_remaining',
        'settings.shipping.free_threshold.label', 'settings.shipping.free_threshold.help',
    ];

    /** The only files that may implement or display free shipping: the threshold rule and the summaries that show it. */
    private const FREE_SHIPPING_THRESHOLD_FILES = [
        'app/Support/Shipping.php',
        'app/Support/CheckoutTotals.php',
        'app/Models/Cart.php',
        'app/Http/Controllers/CartController.php',
        'app/Http/Controllers/CheckoutController.php',
        'config/settings.php',
        'resources/js/components/checkout/ShippingLine.tsx',
        'resources/js/components/checkout/OrderSummary.tsx',
        'resources/js/pages/cart.tsx',
        'resources/js/pages/Checkout/Details.tsx',
        'resources/js/types/settings.d.ts',
    ];

    /** "Free shipping" in each language (any text of the locale, not only the rewritten keys). */
    private const FREE_SHIPPING = [
        'en' => '/free (standard |express )?(shipping|delivery)|(shipping|delivery) (is |for )?free/i',
        'it' => '/(spedizione|consegna) (standard )?(gratuita|gratis)|(gratuita|gratis) (la )?(spedizione|consegna)/iu',
        'de' => '/kostenlos(er|e)? (standard)?(versand|lieferung)|versandkostenfrei|gratis(-| )versand/iu',
        'es' => '/(envío|entrega) (estándar )?(gratis|gratuito|gratuita)|(gratis|gratuito)/iu',
        'fr' => '/(livraison|expédition) (standard )?(gratuite|offerte)|(gratuite|offerte)/iu',
    ];

    private function lang(string $locale): array
    {
        return json_decode(file_get_contents(lang_path("{$locale}.json")), true);
    }

    /** Flat key (as used by PHP and `emails.*`) first, then the nested object the React pages read. */
    private function value(array $lang, string $key): ?string
    {
        if (isset($lang[$key]) && is_string($lang[$key])) {
            return $lang[$key];
        }

        $value = $lang;
        foreach (explode('.', $key) as $part) {
            if (! is_array($value) || ! array_key_exists($part, $value)) {
                return null;
            }
            $value = $value[$part];
        }

        return is_string($value) ? $value : null;
    }

    private function placeholders(string $text): array
    {
        preg_match_all('/\{[a-z_]+\}|:[a-z_]+/i', $text, $matches);
        $found = array_values(array_unique($matches[0]));
        sort($found);

        return $found;
    }

    public function test_every_policy_text_exists_in_every_locale_with_exactly_the_expected_placeholders(): void
    {
        foreach (Locales::supported() as $locale) {
            $lang = $this->lang($locale);

            foreach (self::POLICY_KEYS as $key => $expected) {
                $text = $this->value($lang, $key);
                $this->assertNotNull($text, "{$locale}: {$key} is missing");
                $this->assertNotSame('', trim($text), "{$locale}: {$key} is empty");

                sort($expected);
                $this->assertSame($expected, $this->placeholders($text), "{$locale}: placeholders of {$key}");
            }
        }
    }

    public function test_policy_texts_quote_no_hard_coded_numbers_prices_or_currency_symbols(): void
    {
        foreach (Locales::supported() as $locale) {
            $lang = $this->lang($locale);

            foreach (array_keys(self::POLICY_KEYS) as $key) {
                // the minimum amount is a number in its own right: only the shipping/returns copy is checked for digits
                $text = $this->value($lang, $key);

                $this->assertDoesNotMatchRegularExpression('/[$£]/u', $text, "{$locale}: {$key} has a currency symbol");
                $this->assertDoesNotMatchRegularExpression('/\d/', $text, "{$locale}: {$key} hard-codes a number (use the {days}/{dispatch}/{delivery} placeholders)");
                $this->assertDoesNotMatchRegularExpression('/\bexpress|espress|next[- ]day|giorno successivo|nächsten Tag|día siguiente|lendemain/iu', $text, "{$locale}: {$key} promises express delivery");
            }

            // the amount placeholders of the checkout texts used to carry the currency symbol of the template
            foreach (['checkout.pay', 'cart.discount_applied'] as $key) {
                $this->assertDoesNotMatchRegularExpression('/[$£€]/u', $this->value($lang, $key), "{$locale}: {$key} must not carry a currency symbol");
            }
        }
    }

    public function test_the_removed_keys_are_gone_and_no_text_promises_free_shipping_in_any_locale(): void
    {
        foreach (Locales::supported() as $locale) {
            $lang = $this->lang($locale);

            foreach (self::REMOVED_KEYS as $key) {
                $this->assertNull($this->value($lang, $key), "{$locale}: {$key} should have been removed");
            }

            $strings = [];
            $promises = array_diff_key($lang, array_flip(self::FREE_SHIPPING_THRESHOLD_KEYS));
            array_walk_recursive($promises, function ($value, $key) use (&$strings) {
                if (is_string($value)) {
                    $strings[$key] = $value;
                }
            });

            foreach ($strings as $key => $text) {
                $this->assertDoesNotMatchRegularExpression(self::FREE_SHIPPING[$locale], $text, "{$locale}: {$key} reads like a free-shipping promise");
            }
        }
    }

    public function test_no_free_shipping_copy_or_logic_is_left_in_the_code(): void
    {
        $roots = ['app', 'config', 'database/seeders', 'database/factories', 'resources/js', 'resources/views', 'routes', 'lang'];
        $pattern = '/free[ _-]?shipping|shipping[ _-]?free|calculateShippingCost|\.free[\'"`]/i';
        $hits = [];

        foreach ($roots as $root) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($root), RecursiveDirectoryIterator::SKIP_DOTS));

            foreach ($files as $file) {
                if (! $file->isFile() || ! preg_match('/\.(php|tsx?|json|md)$/', $file->getFilename())) {
                    continue;
                }

                $path = str_replace(base_path().'/', '', $file->getPathname());
                $content = file_get_contents($file->getPathname());

                // the threshold's own texts are allowed in the translation files, nothing else
                if (str_starts_with($path, 'lang/') && str_ends_with($path, '.json')) {
                    $content = json_encode(array_diff_key(json_decode($content, true), array_flip(self::FREE_SHIPPING_THRESHOLD_KEYS)), JSON_UNESCAPED_UNICODE);
                }

                // the old rate-table logic is gone everywhere; the threshold lives only in its own files
                $allowed = in_array($path, self::FREE_SHIPPING_THRESHOLD_FILES, true);
                if (preg_match($allowed ? '/calculateShippingCost/i' : $pattern, $content, $match)) {
                    $hits[] = $path.': '.$match[0];
                }
            }
        }

        $this->assertSame([], $hits, 'free-shipping leftovers');
    }

    public function test_the_withdrawal_days_are_one_readonly_public_value_shared_with_the_frontend(): void
    {
        $definition = app(SettingsRegistry::class)->get('legal.withdrawal_days');

        $this->assertSame(SettingDefinition::ACCESS_READONLY, $definition->access);
        $this->assertTrue($definition->public);
        $this->assertSame((int) config('shipping.withdrawal_days'), settings('legal.withdrawal_days'));
        $this->assertGreaterThanOrEqual(14, settings('legal.withdrawal_days'));

        $this->get('/shipping-returns')->assertInertia(fn (Assert $page) => $page
            ->component('shipping-returns')
            ->where('settings', fn ($settings) => $settings['legal.withdrawal_days'] === (int) config('shipping.withdrawal_days')
                && is_int($settings['shipping.dispatch_days_min']) && is_int($settings['shipping.dispatch_days_max'])
                && is_int($settings['shipping.delivery_days_min']) && is_int($settings['shipping.delivery_days_max'])
                && $settings['store.currency'] === 'EUR'
                && is_string($settings['contact.support_email'])));
    }

    public function test_the_pages_that_quote_the_policy_pass_the_settings_as_placeholders(): void
    {
        $sources = [
            'shipping-returns' => file_get_contents(resource_path('js/pages/shipping-returns.tsx')),
            'faq' => file_get_contents(resource_path('js/pages/faq.tsx')),
            'about' => file_get_contents(resource_path('js/pages/about.tsx')),
            'success' => file_get_contents(resource_path('js/pages/Checkout/Success.tsx')),
        ];

        foreach ($sources as $page => $source) {
            $this->assertStringContainsString('usePolicyValues', $source, "{$page} must read days/times from the settings");
        }

        // the cost table and the other hard-coded numbers are gone from the shipping & returns page
        $this->assertDoesNotMatchRegularExpression('/€\s?\d|<table|August 5, 2025|Important:|Contact for Returns/', $sources['shipping-returns']);
    }
}
