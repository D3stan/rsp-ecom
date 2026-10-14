<?php

use App\Jobs\SendOrderEmail;
use App\Mail\NewOrderAlert;
use App\Mail\OrderCancelled;
use App\Mail\OrderDelivered;
use App\Mail\OrderRefunded;
use App\Mail\PaidOrderMissingAlert;
use App\Models\Order;
use App\Models\OrderEmail;
use App\Models\Setting;
use App\Services\EmailService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Mail\Lifecycle\Support\LifecycleFixtures as Fx;

// ---------------------------------------------------------------- rendering

dataset('lifecycle mails', [
    'cancelled' => [fn (Order $o) => new OrderCancelled($o), true],
    'delivered' => [fn (Order $o) => new OrderDelivered($o), true],
    'refunded (partial)' => [fn (Order $o) => new OrderRefunded($o, 20.00), true],
    'refunded (full)' => [fn (Order $o) => new OrderRefunded($o, 55.00), true],
    'new order alert' => [fn (Order $o) => new NewOrderAlert($o), false],
    'paid order missing' => [fn (Order $o) => new PaidOrderMissingAlert('cs_test_abc', [
        'payment_intent_id' => 'pi_123', 'amount' => '55.00 EUR', 'customer_email' => 'x@example.test', 'error' => 'RuntimeException: boom',
    ]), false],
]);

it('renders HTML and plain text in every shipped locale without leaking keys or placeholders', function (Closure $make, bool $customerFacing, string $locale) {
    $order = Fx::paidOrder(['discount_amount' => 5.00, 'coupon_code' => 'SALE5', 'notes' => 'Citofono rotto']);
    $order->update(['locale' => $locale]);

    Mail::to('someone@example.test')->locale($locale)->send($make($order->fresh(['orderItems.size', 'billingAddress', 'shippingAddress'])));

    $messages = Fx::delivered();
    expect($messages)->toHaveCount(1);
    $mail = $messages[0];
    $html = $mail->getHtmlBody();
    $text = $mail->getTextBody();

    expect($html)->toBeString()->not->toBeEmpty();
    expect($text)->toBeString()->not->toBeEmpty();

    foreach ([$mail->getSubject(), $html, $text] as $output) {
        expect($output)->not->toContain('emails.')            // untranslated key
            ->and($output)->not->toMatch('/(?<![\w\/]):(number|amount|total|address|name|code)\b/'); // unreplaced :placeholder
    }

    // customer-supplied/product text is escaped in HTML and left readable in plain text
    if (str_contains($html, 'Maglia')) {
        expect($html)->toContain('Maglia &lt;b&gt;Test&lt;/b&gt; &amp; Co')->and($html)->not->toContain('<b>Test</b>');
        expect($text)->toContain('Maglia <b>Test</b> & Co');
    }

    // the "automatic message" notice is on customer mails only
    if ($customerFacing) {
        expect($html)->toContain(config('mail.support_address'))->and($text)->toContain(__('emails.no_reply.title', [], $locale));
    } else {
        expect($html)->not->toContain(__('emails.no_reply.title', [], $locale));
    }
})->with('lifecycle mails')->with(['it', 'en']);

it('translates the subject into the order language', function () {
    $order = Fx::paidOrder();

    Mail::to('c@example.test')->locale('it')->send(new OrderCancelled($order));
    Mail::to('c@example.test')->locale('en')->send(new OrderCancelled($order));

    [$it, $en] = Fx::delivered();
    expect($it->getSubject())->toBe("Il tuo ordine #{$order->order_number} è stato annullato")
        ->and($en->getSubject())->toBe("Your order #{$order->order_number} has been cancelled");
});

it('only promises a refund in the cancellation mail when money was captured', function () {
    $paid = Fx::paidOrder();
    $notified = Fx::unpaidOrder();

    Mail::to('c@example.test')->locale('en')->send(new OrderCancelled($paid));
    Mail::to('c@example.test')->locale('en')->send(new OrderCancelled($notified));

    [$withRefund, $withoutRefund] = Fx::delivered();
    expect($withRefund->getTextBody())->toContain('we will refund')->and($withRefund->getTextBody())->toContain('55')
        ->and($withRefund->getTextBody())->not->toMatch('/business days|giorni/');   // no timeline: the refund is manual
    expect($withoutRefund->getTextBody())->not->toContain('refund');
});

it('greets by billing name for guests and falls back to a neutral greeting without a name', function () {
    $named = Fx::paidOrder();
    $anonymous = Fx::paidOrder([
        'billing_address_id' => App\Models\Address::factory()->billing()->create(['first_name' => '', 'last_name' => '']),
        'shipping_address_id' => App\Models\Address::factory()->shipping()->create(['first_name' => '', 'last_name' => '']),
    ]);

    Mail::to('c@example.test')->locale('en')->send(new OrderDelivered($named->fresh()));
    Mail::to('c@example.test')->locale('en')->send(new OrderDelivered($anonymous->fresh()));

    [$a, $b] = Fx::delivered();
    expect($a->getTextBody())->toContain('Hello Mario,');
    expect($b->getTextBody())->toContain("Hello,\n")->and($b->getTextBody())->not->toContain('Hello ,');
});

// ---------------------------------------------------------------- job path (queue -> mail)

it('delivers the delivered and cancelled mails to the customer in the order locale, end to end', function () {
    Mail::fake();

    $delivered = Fx::paidOrder(['status' => 'shipped', 'locale' => 'en']);
    $cancelled = Fx::paidOrder(['guest_email' => 'other@example.test', 'locale' => 'it']);

    $delivered->update(['status' => 'delivered']);   // sync queue: the job runs right away
    $cancelled->cancel();

    Mail::assertSent(OrderDelivered::class, 1);
    Mail::assertSent(OrderDelivered::class, fn ($m) => $m->hasTo('guest@example.test') && $m->locale === 'en');
    Mail::assertSent(OrderCancelled::class, 1);
    Mail::assertSent(OrderCancelled::class, fn ($m) => $m->hasTo('other@example.test') && $m->locale === 'it');
    expect(OrderEmail::whereNotNull('sent_at')->count())->toBe(2);
});

it('renders the lifecycle mails in the order language for every supported locale', function (string $locale, string $subject) {
    config(['app.locale' => 'it']);
    $order = Fx::paidOrder(['status' => 'shipped', 'locale' => $locale]);

    $order->update(['status' => 'delivered']);   // sync queue: renders and "sends" through the array transport

    [$mail] = Fx::delivered();
    expect($mail->getSubject())->toBe(str_replace('{number}', "#{$order->order_number}", $subject))
        ->and($mail->getHtmlBody())->not->toContain('emails.')
        ->and($mail->getTextBody())->not->toContain('emails.');
})->with([
    'de' => ['de', 'Ihre Bestellung {number} wurde zugestellt'],
    'es' => ['es', 'Su pedido {number} ha sido entregado'],
    'fr' => ['fr', 'Votre commande {number} a été livrée'],
]);

it('uses the account email for registered customers', function () {
    Mail::fake();
    $user = App\Models\User::factory()->create(['email' => 'account@example.test', 'name' => 'Anna Verdi']);
    $order = Fx::paidOrder(['user_id' => $user->id, 'guest_email' => null, 'status' => 'shipped']);

    $order->update(['status' => 'delivered']);

    Mail::assertSent(OrderDelivered::class, fn ($m) => $m->hasTo('account@example.test'));
});

// ---------------------------------------------------------------- refund (built, not wired)

it('queues one refund email per distinct amount and carries the amount to the job', function () {
    Queue::fake();
    $order = Fx::paidOrder();
    $service = app(EmailService::class);

    expect($service->sendOrderRefunded($order, 20.00))->toBeTrue();
    expect($service->sendOrderRefunded($order, 20.00))->toBeTrue();   // replay: nothing new
    expect($service->sendOrderRefunded($order, 35.00))->toBeTrue();   // a second, different refund

    Queue::assertPushed(SendOrderEmail::class, 2);
    Queue::assertPushed(SendOrderEmail::class, fn ($job) => $job->type === OrderEmail::TYPE_REFUNDED && $job->amount === '20.00');
    Queue::assertPushed(SendOrderEmail::class, fn ($job) => $job->type === OrderEmail::TYPE_REFUNDED && $job->amount === '35.00');
    expect(OrderEmail::where('type', OrderEmail::TYPE_REFUNDED)->pluck('fingerprint')->unique())->toHaveCount(2);
});

it('sends the refund email with the right amount when the job runs', function () {
    Mail::fake();
    $order = Fx::paidOrder();

    app(EmailService::class)->sendOrderRefunded($order, 20.00);

    Mail::assertSent(OrderRefunded::class, fn ($m) => $m->hasTo('guest@example.test') && $m->refundAmount === 20.0);
});

it('refuses nonsensical refund amounts and honours the refund switch', function () {
    Queue::fake();
    $order = Fx::paidOrder();
    $service = app(EmailService::class);

    expect($service->sendOrderRefunded($order, 0))->toBeFalse()
        ->and($service->sendOrderRefunded($order, -5))->toBeFalse()
        ->and($service->sendOrderRefunded($order, 55.01))->toBeFalse();

    settings()->set('email.order_refunded', false);
    expect($service->sendOrderRefunded($order, 10))->toBeTrue();   // "accepted": deliberately off

    Queue::assertNothingPushed();
    expect(OrderEmail::count())->toBe(0);
});

// ---------------------------------------------------------------- admin alert recipient

it('sends the new-order alert to the admin address, falling back to the contact address', function () {
    Mail::fake();
    config(['mail.contact_email' => 'contact@shop.test']);
    $order = Fx::paidOrder();

    settings()->set('contact.admin_notification_email', 'owner@shop.test');
    app(EmailService::class)->sendNewOrderAlert($order);
    Mail::assertSent(NewOrderAlert::class, fn ($m) => $m->hasTo('owner@shop.test') && ! $m->hasTo('guest@example.test'));

    $second = Fx::paidOrder(['guest_email' => 'g2@example.test']);
    settings()->set('contact.admin_notification_email', '');
    app(EmailService::class)->sendNewOrderAlert($second);
    Mail::assertSent(NewOrderAlert::class, fn ($m) => $m->hasTo('contact@shop.test'));

    $third = Fx::paidOrder(['guest_email' => 'g3@example.test']);
    // an invalid address cannot be saved through the settings service: simulate a bad row written by hand
    Setting::updateOrCreate(['key' => 'contact.admin_notification_email'], ['value' => json_encode('not-an-email'), 'type' => 'email']);
    app(EmailService::class)->sendNewOrderAlert($third);
    Mail::assertSent(NewOrderAlert::class, 3);
    Mail::assertSent(NewOrderAlert::class, fn ($m) => $m->hasTo('contact@shop.test') && $m->order->is($third));
});

it('sends the new-order alert even for an order without a customer address, once per order', function () {
    Queue::fake();
    config(['mail.contact_email' => 'contact@shop.test']);
    $order = Fx::paidOrder(['guest_email' => null]);
    $service = app(EmailService::class);

    expect($service->sendNewOrderAlert($order))->toBeTrue();
    expect($service->sendNewOrderAlert($order))->toBeTrue();

    Queue::assertPushed(SendOrderEmail::class, 1);
});

it('honours the master switch for the new-order alert', function () {
    Queue::fake();
    settings()->set('email.enabled', false);

    app(EmailService::class)->sendNewOrderAlert(Fx::paidOrder());

    Queue::assertNothingPushed();
    expect(OrderEmail::count())->toBe(0);
});

it('renders the admin alert in the app locale, not the customer locale', function () {
    Mail::fake();
    config(['app.locale' => 'it', 'mail.contact_email' => 'contact@shop.test']);
    $order = Fx::paidOrder(['locale' => 'en']);

    app(EmailService::class)->sendNewOrderAlert($order);

    Mail::assertSent(NewOrderAlert::class, fn ($m) => $m->locale === 'it');
});
