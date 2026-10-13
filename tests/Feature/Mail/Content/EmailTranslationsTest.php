<?php

/**
 * Every email string lives under the flat `emails.*` keys of lang/{it,en,de,es,fr}.json.
 * These tests keep the five files in lock-step and make sure nothing is used without being translated.
 */
const EMAIL_LOCALES = ['it', 'en', 'de', 'es', 'fr'];

function emailKeys(string $locale): array
{
    $strings = json_decode(file_get_contents(lang_path("{$locale}.json")), true, flags: JSON_THROW_ON_ERROR);

    return array_filter($strings, fn ($value, $key) => str_starts_with($key, 'emails.'), ARRAY_FILTER_USE_BOTH);
}

function placeholdersOf(string $text): array
{
    preg_match_all('/:[A-Za-z_]+/', $text, $matches);
    $found = $matches[0];
    sort($found);

    return $found;
}

/**
 * Every emails.* key literal that appears in the code that renders emails.
 *
 * @return array<int, string>
 */
function emailKeysUsedInCode(): array
{
    $files = [];

    foreach ([resource_path('views/emails'), app_path('Mail'), app_path('Notifications'), app_path('Jobs')] as $dir) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
            if (str_ends_with($file->getFilename(), '.php')) {
                $files[] = $file->getPathname();
            }
        }
    }

    $used = [];

    foreach ($files as $file) {
        // contact-message is the replyable notification to the shop owner: not translated on purpose
        if (preg_match_all("/['\"](emails\.[a-z0-9_]+(?:\.[a-z0-9_]+)+)['\"]/", file_get_contents($file), $matches)) {
            // view names (emails.partials.items, emails.layouts.base) look like keys but are not
            $used = array_merge($used, array_filter($matches[1], fn ($key) => ! preg_match('/^emails\.(layouts|partials)\./', $key)));
        }
    }

    return array_values(array_unique($used));
}

it('has the same emails.* keys in all five locale files', function () {
    $reference = array_keys(emailKeys('en'));
    expect($reference)->not->toBeEmpty();

    foreach (EMAIL_LOCALES as $locale) {
        $keys = array_keys(emailKeys($locale));

        expect(array_diff($reference, $keys))->toBe([], "{$locale} is missing keys")
            ->and(array_diff($keys, $reference))->toBe([], "{$locale} has keys that en does not");
    }
});

it('has no empty emails.* value in any locale', function () {
    foreach (EMAIL_LOCALES as $locale) {
        foreach (emailKeys($locale) as $key => $value) {
            expect($value)->toBeString()->and(trim($value))->not->toBe('', "{$locale} {$key} is empty");
        }
    }
});

it('uses identical :placeholders for every key in every locale', function () {
    $reference = emailKeys('en');

    foreach (EMAIL_LOCALES as $locale) {
        foreach (emailKeys($locale) as $key => $value) {
            expect(placeholdersOf($value))->toBe(placeholdersOf($reference[$key]), "{$locale} {$key}: placeholders differ from en");
        }
    }
});

it('translates every emails.* key used by the views, mailables and notifications, and uses every key it ships', function () {
    $reference = array_keys(emailKeys('en'));
    $used = emailKeysUsedInCode();

    expect(array_diff($used, $reference))->toBe([], 'keys used in code but missing from the language files');
    expect(array_diff($reference, $used))->toBe([], 'keys shipped but never used (dead strings)');
});

it('does not translate by hand: the email views carry no literal Italian or English sentences', function () {
    $suspects = ['Ciao ', 'Cliente', 'Spedizione', 'Hello ', 'Order ', 'Your order', 'Conferma', 'Visualizza', 'Resi', 'Thank you'];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views/emails'), FilesystemIterator::SKIP_DOTS)) as $file) {
        // the contact-form notification is internal and intentionally left as it was
        if ($file->getFilename() === 'contact-message.blade.php' || ! str_ends_with($file->getFilename(), '.php')) {
            continue;
        }

        // drop {{ ... }} / {!! ... !!} / @directives(...) so only literal template text is checked
        $literal = preg_replace(['/\{\{--.*?--\}\}/s', '/\{\{.*?\}\}/s', '/\{!!.*?!!\}/s', '/@[a-zA-Z]+\(.*?\)\n/s', '/<style.*?<\/style>/s', '/<!--.*?-->/s', '/<[^>]+>/s'], ' ', file_get_contents($file->getPathname()));

        foreach ($suspects as $word) {
            expect($literal)->not->toContain($word, "{$file->getFilename()} contains the literal text \"{$word}\"");
        }
    }
});
