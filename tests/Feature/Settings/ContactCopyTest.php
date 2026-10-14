<?php

// Contact details live in the admin settings (contact.*, store.*), never in the copy or the pages.

const CONTACT_COPY_LOCALES = ['en', 'it', 'de', 'es', 'fr'];

function contactCopyLang(string $locale): array
{
    return json_decode(file_get_contents(lang_path("{$locale}.json")), true, flags: JSON_THROW_ON_ERROR);
}

function contactCopyPlaceholders(string $text): array
{
    preg_match_all('/\{(\w+)\}/', $text, $m);
    sort($m[1]);

    return array_values(array_unique($m[1]));
}

it('ships no hard-coded support address, phone number or fake street in the language files or the pages', function () {
    $forbidden = ['rsp-industries.com', '(555)', '123 Business Street', 'EST.'];
    $files = array_merge(
        array_map(fn ($l) => lang_path("{$l}.json"), CONTACT_COPY_LOCALES),
        glob(resource_path('js/pages/*.tsx')),
        [resource_path('js/layouts/public-layout.tsx')],
    );

    foreach ($files as $file) {
        $content = file_get_contents($file);
        foreach ($forbidden as $needle) {
            expect(str_contains($content, $needle))->toBeFalse(basename($file)." contains {$needle}");
        }
    }
});

it('quotes the support email through the {email} placeholder in every locale', function () {
    foreach (CONTACT_COPY_LOCALES as $locale) {
        $lang = contactCopyLang($locale);

        foreach ([['privacy', 'contact_email'], ['terms', 'contact_email'], ['shipping_returns', 'contact_email']] as [$section, $key]) {
            expect($lang[$section][$key])->toContain('{email}');
        }
        expect($lang['faq_items']['ordering']['answer_2'])->toContain('{email}')
            ->and($lang['faq_items']['general']['answer_1'])->toContain('{email}');
    }
});

it('keeps the placeholders of the contact copy identical across locales', function () {
    $keys = [
        ['faq_items', 'ordering', 'answer_2'],
        ['faq_items', 'general', 'answer_1'],
        ['faq_items', 'general', 'answer_4'],
        ['faq_items', 'general', 'answer_4_no_address'],
        ['faq_contact_phone_line'],
        ['faq_contact_hours_line'],
        ['footer_vat_number'],
        ['product', 'max_per_order'],
        ['guest_order', 'invalid_body'],
    ];
    $en = contactCopyLang('en');

    foreach ($keys as $path) {
        $value = fn (array $lang) => array_reduce($path, fn ($carry, $segment) => $carry[$segment], $lang);

        foreach (CONTACT_COPY_LOCALES as $locale) {
            expect(contactCopyPlaceholders($value(contactCopyLang($locale))))
                ->toBe(contactCopyPlaceholders($value($en)), implode('.', $path)." in {$locale}");
        }
    }
});

it('has the same PHP-side flat keys, with the same :placeholders, in every locale', function () {
    foreach (['cart.max_qty_exceeded', 'reviews.thank_you', 'reviews.pending_moderation'] as $key) {
        $reference = null;

        foreach (CONTACT_COPY_LOCALES as $locale) {
            $text = contactCopyLang($locale)[$key] ?? null;
            expect($text)->not->toBeNull("{$key} missing in {$locale}");

            preg_match_all('/:\w+/', $text, $m);
            $reference ??= $m[0];
            expect($m[0])->toBe($reference, "{$key} in {$locale}");
        }
    }
});
