<?php

use App\Mail\OrderCancelled;
use App\Mail\OrderConfirmation;
use App\Mail\OrderDelivered;
use App\Mail\OrderShipped;
use App\Mail\Traits\DescribesOrder;
use App\Mail\Traits\HasLogo;
use App\Models\Order;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\Feature\Mail\Content\Support\EmailFixtures as Fx;

/** Exposes the (protected) formatting helpers of DescribesOrder. */
function describer(): object
{
    return new class
    {
        use DescribesOrder, HasLogo;

        public function totals(Order $order, bool $breakdown = true): array
        {
            return $this->orderTotals($order, $breakdown);
        }

        public function fmt(float $amount, ?string $currency = 'EUR'): string
        {
            return $this->money($amount, $currency);
        }
    };
}

/** "€1,234.56" / "-1.234,56 €" -> float, for any of the supported formats. */
function amountOf(string $formatted): float
{
    $negative = str_contains($formatted, '-');
    $digits = preg_replace('/[^\d.,]/u', '', $formatted);
    // the last separator is the decimal one
    $decimal = max(strrpos($digits, '.'), strrpos($digits, ','));
    $number = (float) (preg_replace('/\D/', '', substr($digits, 0, $decimal)).'.'.substr($digits, $decimal + 1));

    return $negative ? -$number : $number;
}

// ---------------------------------------------------------------- money

it('formats amounts per locale without needing intl', function () {
    $nbsp = "\u{00A0}";
    $cases = [
        ['it', 1234.56, "1.234,56{$nbsp}€"],
        ['de', 1234.56, "1.234,56{$nbsp}€"],
        ['es', 1234.56, "1234,56{$nbsp}€"],
        ['es', 12345.6, "12.345,60{$nbsp}€"],
        ['fr', 1234.56, "1\u{202F}234,56{$nbsp}€"],
        ['en', 1234.56, '€1,234.56'],
        ['en', 0, '€0.00'],
        ['it', 0.5, "0,50{$nbsp}€"],
    ];

    foreach ($cases as [$locale, $amount, $expected]) {
        app()->setLocale($locale);
        expect(describer()->fmt($amount))->toBe($expected);
    }

    app()->setLocale('en');
    expect(describer()->fmt(10, 'usd'))->toBe('$10.00')
        ->and(describer()->fmt(10, 'gbp'))->toBe('£10.00')
        ->and(describer()->fmt(10, 'chf'))->toBe("CHF{$nbsp}10.00")
        ->and(describer()->fmt(10, null))->toBe('€10.00');
});

// ---------------------------------------------------------------- totals

it('always shows lines that add up to the stored total', function (array $attributes) {
    app()->setLocale('en');
    $order = Fx::order($attributes);
    $totals = describer()->totals($order);

    $values = collect($totals['rows'])->mapWithKeys(fn ($row) => [$row['label'] => $row['value']]);
    $subtotal = amountOf($values['Subtotal']);
    $discountLine = $values->first(fn ($value, $label) => str_starts_with($label, 'Discount'));
    $discount = $discountLine !== null ? amountOf($discountLine) : 0.0;
    $shipping = $values['Shipping'] === 'Free' ? 0.0 : amountOf($values['Shipping']);
    $total = amountOf($values['Total']);

    expect(round($subtotal + $discount + $shipping, 2))->toBe(round($total, 2))
        ->and($total)->toBe(round((float) $order->total_amount, 2));
})->with([
    'no discount' => [[]],
    'coupon' => [['discount_amount' => 6.00, 'coupon_code' => 'PRIMAVERA10', 'total_amount' => 59.00]],
    'free shipping' => [['shipping_amount' => 0, 'total_amount' => 60.00]],
    '100% discount' => [['discount_amount' => 65.00, 'coupon_code' => 'FREE100', 'total_amount' => 0]],
    'legacy row whose parts do not add up' => [['subtotal' => 65.00, 'shipping_amount' => 5.00, 'discount_amount' => 0, 'total_amount' => 65.00]],
    'legacy row with a bigger total than its parts' => [['subtotal' => 10.00, 'shipping_amount' => 0, 'discount_amount' => 0, 'total_amount' => 65.00]],
]);

it('shows the coupon code, localised "free" shipping and never a VAT amount', function () {
    $order = Fx::discountedOrder(['shipping_amount' => 0, 'total_amount' => 54.00]);

    foreach (Fx::LOCALES as $locale) {
        $html = Fx::deliver(new OrderConfirmation($order->fresh()), $locale)->getHtmlBody();

        expect($html)->toContain('PRIMAVERA10')
            ->toContain(__('emails.common.shipping_free', [], $locale))
            ->toContain(__('emails.common.vat_included', [], $locale))
            ->not->toContain('22%')->not->toContain('IVA (');
    }
});

it('does not hard-code a VAT rate or read the always-zero tax_amount', function () {
    $order = Fx::order(['tax_amount' => 0]);
    $text = Fx::deliver(new OrderConfirmation($order), 'it')->getTextBody();

    $totalsBlock = Str::between($text, 'Subtotale:', '(IVA inclusa)');

    expect($text)->toContain('(IVA inclusa)')->not->toContain('%')->not->toMatch('/IVA\s*\(\d/');
    expect($totalsBlock)->not->toContain('22')->not->toContain('IVA');

    // a populated tax_amount would not change what is displayed either
    $withTax = Fx::order(['tax_amount' => 11.72]);
    expect(Fx::deliver(new OrderConfirmation($withTax), 'it')->getTextBody())->not->toContain('11,72');
});

it('shows only the total on the cancellation mail', function () {
    $order = Fx::discountedOrder();
    $text = Fx::deliver(new OrderCancelled($order), 'en')->getTextBody();

    expect($text)->toContain('Total: ')->not->toContain('Subtotal');
});

it('lists size, variant and SKU on the item lines, and survives a deleted product', function () {
    $text = Fx::deliver(new OrderConfirmation(Fx::order()), 'en')->getTextBody();

    expect($text)->toContain('Variant: Rosso')->toContain('Size: M')->toContain('SKU: FAR-AE-R')
        ->toContain('Qty 2 × €25.00 = €50.00')
        ->toContain('Guarnizione di ricambio')->toContain('Qty 1 × €10.00 = €10.00');
});

it('uses an absolute placeholder image when the product has no picture', function () {
    $html = Fx::deliver(new OrderConfirmation(Fx::order()), 'en')->getHtmlBody();

    expect($html)->toContain('src="'.asset('images/product.png').'"');
});

// ---------------------------------------------------------------- links for guests / members

it('links guests to the signed order page and members to their order list', function () {
    $guest = Fx::order();
    $member = Fx::order([], registered: true);

    foreach ([OrderConfirmation::class, OrderShipped::class, OrderDelivered::class] as $class) {
        $guestHtml = Fx::deliver(new $class($guest), 'en')->getHtmlBody();
        preg_match('#href="(http[^"]*/orders/'.preg_quote($guest->order_number, '#').'[^"]*)"#', html_entity_decode($guestHtml), $m);

        expect($m)->not->toBeEmpty("{$class} has no order link for the guest");
        $url = $m[1];
        expect($url)->toContain('signature=')->toContain('expires=');
        expect(URL::hasValidSignature(Illuminate\Http\Request::create($url)))->toBeTrue();

        $memberHtml = Fx::deliver(new $class($member), 'en')->getHtmlBody();
        expect($memberHtml)->toContain(route('orders.index'))->not->toContain('signature=')->not->toContain('/orders/'.$member->order_number);
    }
});

it('serves the guest link from the email: a valid signature opens the order page', function () {
    $order = Fx::order();
    $html = html_entity_decode(Fx::deliver(new OrderConfirmation($order), 'it')->getHtmlBody());
    preg_match('#href="(http[^"]*/orders/[^"]*)"#', $html, $m);

    $this->get($m[1])->assertOk();
});

// ---------------------------------------------------------------- tracking

it('builds the tracking url from the carrier template in config, null otherwise', function () {
    $order = Fx::order(['tracking_number' => 'AB 12/34']);

    // no carrier column / value yet
    expect($order->trackingUrl())->toBeNull();

    config(['shipping.carriers' => ['gls' => ['name' => 'GLS', 'tracking_url' => 'https://track.example.test/?n={number}']]]);
    expect($order->trackingUrl())->toBeNull();                      // still no carrier on the order

    $order->setAttribute('tracking_carrier', 'gls');
    expect($order->trackingUrl())->toBe('https://track.example.test/?n=AB%2012%2F34')
        ->and($order->trackingUrl('XYZ'))->toBe('https://track.example.test/?n=XYZ')
        ->and($order->trackingCarrierName())->toBe('GLS');

    $order->setAttribute('tracking_carrier', 'unknown');
    expect($order->trackingUrl())->toBeNull()->and($order->trackingCarrierName())->toBe('unknown');

    // unusable templates are ignored
    config(['shipping.carriers' => ['gls' => ['tracking_url' => 'https://track.example.test/'], 'js' => ['tracking_url' => 'javascript:alert({number})']]]);
    $order->setAttribute('tracking_carrier', 'gls');
    expect($order->trackingUrl())->toBeNull();
    $order->setAttribute('tracking_carrier', 'js');
    expect($order->trackingUrl())->toBeNull();

    // no number, no link
    config(['shipping.carriers' => ['gls' => ['tracking_url' => 'https://track.example.test/?n={number}']]]);
    $order->setAttribute('tracking_carrier', 'gls');
    $order->tracking_number = null;
    expect($order->trackingUrl())->toBeNull();
});

it('ships with an empty carrier list (no invented carrier urls)', function () {
    expect(config('shipping.carriers'))->toBe([]);
});

it('shows a tracking button only when the carrier page is known, and the number alone otherwise', function () {
    $order = Fx::order(['tracking_number' => 'TRK-998877']);

    $without = Fx::deliver(new OrderShipped($order, 'TRK-998877'), 'en');
    expect($without->getHtmlBody())->toContain('TRK-998877')->not->toContain(__('emails.order_shipped.track_button', [], 'en'));
    expect($without->getTextBody())->toContain('Tracking number: TRK-998877')->not->toContain('Track your shipment');

    config(['shipping.carriers' => ['gls' => ['name' => 'GLS', 'tracking_url' => 'https://track.example.test/?n={number}']]]);
    $order->setAttribute('tracking_carrier', 'gls');

    $with = Fx::deliver(new OrderShipped($order, 'TRK-998877'), 'en');
    expect($with->getHtmlBody())->toContain('href="https://track.example.test/?n=TRK-998877"')->toContain('Track your shipment')->toContain('GLS');
    expect($with->getTextBody())->toContain('Track your shipment: https://track.example.test/?n=TRK-998877')->toContain('Carrier: GLS');
});

it('says so when a shipment has no tracking number and never has a dead link sentence', function () {
    foreach (Fx::LOCALES as $locale) {
        $mail = Fx::deliver(new OrderShipped(Fx::order(), null), $locale);

        expect($mail->getTextBody())->toContain(__('emails.order_shipped.no_tracking', [], $locale))
            ->not->toContain('Usa questo link');
    }
});

// ---------------------------------------------------------------- delivery estimate & withdrawal

it('takes the delivery estimate from one config source in both the confirmation and the shipped mail', function () {
    config(['shipping.delivery_estimate' => ['dispatch_min' => 2, 'dispatch_max' => 3, 'delivery_min' => 4, 'delivery_max' => 6]]);
    $order = Fx::order();

    $confirmation = Fx::deliver(new OrderConfirmation($order), 'en')->getTextBody();
    $shipped = Fx::deliver(new OrderShipped($order, 'T1'), 'en')->getTextBody();

    expect($confirmation)->toContain('2-3 business days')->toContain('4-6 business days');
    expect($shipped)->toContain('4-6 business days')->not->toContain('3-5')->not->toContain('2-5');

    // and a single day when min = max
    config(['shipping.delivery_estimate' => ['dispatch_min' => 1, 'dispatch_max' => 1, 'delivery_min' => 3, 'delivery_max' => 3]]);
    expect(Fx::deliver(new OrderConfirmation($order), 'en')->getTextBody())->toContain('within 1 business days')->toContain('takes 3 business days');
});

it('states the withdrawal right with links in the confirmation and shipped mails only', function () {
    $order = Fx::order();

    foreach (Fx::LOCALES as $locale) {
        foreach ([new OrderConfirmation($order), new OrderShipped($order, 'T1')] as $mailable) {
            $mail = Fx::deliver($mailable, $locale);
            $title = __('emails.withdrawal.title', [], $locale);

            foreach ([$mail->getHtmlBody(), $mail->getTextBody()] as $output) {
                expect($output)->toContain($title)->toContain('14')
                    ->toContain(route('terms'))->toContain(route('shipping-returns'))->toContain(route('privacy'));
            }
        }

        foreach ([new OrderCancelled($order), new OrderDelivered($order)] as $mailable) {
            expect(Fx::deliver($mailable, $locale)->getTextBody())->not->toContain(__('emails.withdrawal.title', [], $locale));
        }
    }

    config(['shipping.withdrawal_days' => 30]);
    expect(Fx::deliver(new OrderConfirmation($order), 'en')->getTextBody())->toContain('within 30 days');
});

// ---------------------------------------------------------------- notice

it('puts a readable automatic-message notice in the customer mails but not in the contact notification', function () {
    config(['mail.support_address' => 'help@shop.test']);
    $html = Fx::deliver(new OrderConfirmation(Fx::order()), 'en')->getHtmlBody();

    // 14px text in a bordered, tinted box with both a mailto and the contact page
    preg_match('/<table[^>]*>\s*<tr>\s*<td[^>]*background-color: #f3f4f6[^>]*>.*?<\/td>/s', $html, $notice);
    expect($notice)->not->toBeEmpty();
    expect($notice[0])->toContain('border:')->toContain('font-size: 14px')->toContain('mailto:help@shop.test')->toContain(route('contact'));

    $contact = Fx::deliver(new App\Mail\ContactMessage([
        'firstName' => 'A', 'lastName' => 'B', 'email' => 'visitor@example.test', 'subject' => 'Hi', 'message' => 'Hello', 'phone' => null,
    ]), 'en');
    expect($contact->getHtmlBody())->not->toContain(__('emails.no_reply.title', [], 'en'));
    expect($contact->getHeaders()->get('Reply-To'))->not->toBeNull();
});
