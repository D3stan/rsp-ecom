<?php

use App\Mail\NewOrderAlert;
use App\Mail\OrderCancelled;
use App\Mail\OrderConfirmation;
use App\Mail\OrderDelivered;
use App\Mail\OrderRefunded;
use App\Mail\OrderShipped;
use App\Mail\PaidOrderMissingAlert;
use App\Mail\TestEmail;
use App\Mail\WelcomeEmail;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Tests\Feature\Mail\Content\Support\EmailFixtures as Fx;

/**
 * Every mailable x 5 locales x (guest/registered, discount/none, with/without tracking, missing billing name).
 */

/** @return array<string, array{0: Closure, 1: bool}> mailable factory (order) and whether it is customer facing */
function orderMailables(): array
{
    return [
        'confirmation' => [fn ($o) => new OrderConfirmation($o), true],
        'shipped with tracking' => [fn ($o) => new OrderShipped($o, 'TRK-123456'), true],
        'shipped without tracking' => [fn ($o) => new OrderShipped($o, null), true],
        'cancelled' => [fn ($o) => new OrderCancelled($o), true],
        'delivered' => [fn ($o) => new OrderDelivered($o), true],
        'refunded partial' => [fn ($o) => new OrderRefunded($o, 10.00), true],
        'refunded full' => [fn ($o) => new OrderRefunded($o, 59.00), true],
        'admin new order' => [fn ($o) => new NewOrderAlert($o), false],
    ];
}

/** @return array<string, Closure> */
function orderScenarios(): array
{
    return [
        'guest, no discount' => fn () => Fx::order(),
        'guest, discount' => fn () => Fx::discountedOrder(),
        'registered, no discount' => fn () => Fx::order([], registered: true),
        'registered, discount' => fn () => Fx::discountedOrder([], registered: true),
        'guest, no billing name' => fn () => Fx::order([], withBillingName: false),
        'zero shipping' => fn () => Fx::order(['shipping_amount' => 0, 'total_amount' => 60.00]),
        'with stored VAT' => fn () => Fx::order(['tax_rate' => 22, 'tax_amount' => 11.72]),
        'everything discounted' => fn () => Fx::order(['discount_amount' => 65.00, 'coupon_code' => 'FREE100', 'total_amount' => 0]),
    ];
}

function visibleLeaks(string $output): array
{
    $leaks = [];

    if (preg_match('/\b(emails|guest_order)\.[a-z_]+(\.[a-z_]+)*/', $output, $m)) {
        $leaks[] = 'raw translation key: '.$m[0];
    }

    if (preg_match('/(?<![\w\/]):(number|amount|total|address|name|code|days|hours|minutes|dispatch|delivery|min|max|app|carrier|price|quantity|size|variant|sku|value|rate)\b/', $output, $m)) {
        $leaks[] = 'unreplaced placeholder: '.$m[0];
    }

    return $leaks;
}

/**
 * Everything that must hold for one rendered mail; returns what is wrong (empty = fine).
 *
 * @return array<int, string>
 */
function problemsWith(Mailable $mailable, string $locale, bool $customerFacing, Model $order, string $label): array
{
    $mail = Fx::deliver($mailable, $locale);
    $html = (string) $mail->getHtmlBody();
    $text = (string) $mail->getTextBody();
    $problems = [];
    $check = function (bool $ok, string $what) use (&$problems, $label) {
        if (! $ok) {
            $problems[] = "{$label}: {$what}";
        }
    };

    $check($html !== '' && $text !== '', 'empty html or text part');

    foreach (['subject' => (string) $mail->getSubject(), 'html' => $html, 'text' => $text] as $part => $output) {
        foreach (visibleLeaks($output) as $leak) {
            $check(false, "{$part} {$leak}");
        }
    }

    // the order number is the one reference, in the subject and in the body
    $check(str_contains((string) $mail->getSubject(), $order->order_number), 'order number missing from the subject');
    $check(str_contains($text, $order->order_number), 'order number missing from the text');

    // product text is escaped in HTML and readable in plain text; the long name is not cut
    if (! $mailable instanceof OrderRefunded) {   // the refund notice does not list the items
        $check(str_contains($html, e(Fx::LONG_NAME)) && ! str_contains($html, '<b>Pro</b>'), 'product name not escaped/complete in html');
        $check(str_contains($text, Fx::LONG_NAME), 'product name missing from text');
    }

    // Gmail/Outlook-safe: layout by tables, no grid/flex/variables/background images
    foreach (['display: grid', 'display:grid', 'display: flex', 'display:flex', 'var(--', 'background-image'] as $unsafe) {
        $check(! str_contains($html, $unsafe), "html uses {$unsafe}");
    }
    $check(str_contains($html, '<table') && str_contains($html, 'alt="'), 'no table layout or alt text');

    // no marketing: no shop/continue-shopping links
    $check(! str_contains($html, route('products')) && ! str_contains($text, route('products')), 'links to the shop');

    // the automatic-message notice: customer mails only, in the recipient's language
    $notice = __('emails.no_reply.title', [], $locale);
    if ($customerFacing) {
        $check(str_contains($html, $notice) && str_contains($html, 'mailto:help@shop.test') && str_contains($html, route('contact')), 'html notice incomplete');
        $check(str_contains($text, $notice) && str_contains($text, 'help@shop.test'), 'text notice incomplete');
    } else {
        $check(! str_contains($html, $notice) && ! str_contains($text, $notice), 'internal mail carries the customer notice');
    }

    // every link is absolute (or mailto), never empty or "#"
    preg_match_all('/href="([^"]*)"/', $html, $hrefs);
    foreach ($hrefs[1] as $href) {
        $check((bool) preg_match('#^(https?://|mailto:)#', $href), "bad link [{$href}]");
    }

    return $problems;
}

it('renders every order mail for every order scenario in each locale', function (string $locale) {
    config(['mail.support_address' => 'help@shop.test']);
    $problems = [];

    foreach (orderMailables() as $mailName => [$make, $customerFacing]) {
        foreach (orderScenarios() as $scenarioName => $scenario) {
            $order = $scenario();
            $order->update(['locale' => $locale]);

            $problems = array_merge($problems, problemsWith($make($order->fresh()), $locale, $customerFacing, $order, "{$mailName} / {$scenarioName} / {$locale}"));
        }
    }

    expect($problems)->toBe([]);
})->with(Fx::LOCALES);

it('renders the account and internal mails in every locale', function (string $locale) {
    config(['mail.support_address' => 'help@shop.test']);
    $user = User::factory()->create(['name' => 'Anna Verdi']);

    $cases = [
        'welcome' => [new WelcomeEmail($user), true],
        'welcome (no name)' => [new WelcomeEmail(User::factory()->create(['name' => ''])), true],
        'paid order missing' => [new PaidOrderMissingAlert('cs_test_abc', ['payment_intent_id' => 'pi_1', 'amount' => '59.00 EUR', 'customer_email' => 'x@example.test', 'error' => 'RuntimeException: boom']), false],
        'test email' => [new TestEmail, false],
    ];

    foreach ($cases as $name => [$mailable, $customerFacing]) {
        $mail = Fx::deliver($mailable, $locale);

        foreach (['subject' => $mail->getSubject(), 'html' => $mail->getHtmlBody(), 'text' => $mail->getTextBody()] as $part => $output) {
            expect($output)->toBeString()->not->toBeEmpty();
            expect(visibleLeaks($output))->toBe([], "{$name} {$part} leaks");
        }

        $notice = __('emails.no_reply.title', [], $locale);
        expect($mail->getTextBody() && str_contains($mail->getTextBody(), $notice))->toBe($customerFacing, $name);
        expect(str_contains($mail->getHtmlBody(), $notice))->toBe($customerFacing, $name);
    }
})->with(Fx::LOCALES);

it('greets by first name from the billing address for guests, by account name for members, and localises the generic fallback', function () {
    $guest = Fx::order();                                            // billing Mario Rossi
    $anonymous = Fx::order([], withBillingName: false);               // blank billing name, shipping name Maria Giovanna
    $member = Fx::order([], registered: true);                       // Anna Verdi

    expect(Fx::deliver(new OrderConfirmation($guest), 'it')->getTextBody())->toContain("Ciao Mario,\n");
    expect(Fx::deliver(new OrderConfirmation($guest), 'de')->getTextBody())->toContain("Hallo Mario,\n");
    expect(Fx::deliver(new OrderConfirmation($member), 'fr')->getTextBody())->toContain("Bonjour Anna Verdi,\n");
    // shipping name is the next fallback
    expect(Fx::deliver(new OrderConfirmation($anonymous->fresh()), 'es')->getTextBody())->toContain("Hola Maria Giovanna,\n");

    $nameless = Fx::order([], withBillingName: false);
    $nameless->shippingAddress->update(['first_name' => '', 'last_name' => '']);
    $texts = collect(Fx::LOCALES)->mapWithKeys(fn ($l) => [$l => Fx::deliver(new OrderConfirmation($nameless->fresh()), $l)->getTextBody()]);

    expect($texts['it'])->toContain("Ciao,\n")->not->toContain('Cliente');
    expect($texts['en'])->toContain("Hello,\n");
    expect($texts['de'])->toContain("Guten Tag,\n");
    expect($texts['es'])->toContain("Hola,\n");
    expect($texts['fr'])->toContain("Bonjour,\n");
});
