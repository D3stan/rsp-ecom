<?php

use App\Listeners\StripeWebhookListener;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Laravel\Cashier\Events\WebhookReceived;

beforeEach(function () {
    Http::preventStrayRequests();
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
    config(['services.telegram.token' => 'secret-token', 'services.telegram.chat_id' => '4242']);
});

function failedLogin(string $email = 'someone@example.com'): void
{
    event(new Failed('web', null, ['email' => $email, 'password' => 'wrong']));
}

describe('failed logins', function () {
    it('alerts once when one address keeps failing, naming only a masked address', function () {
        foreach (range(1, 12) as $ignored) {
            failedLogin();
        }

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => str_contains($request['text'], '127.0.0.x') && ! str_contains($request['text'], '127.0.0.1'));
    });

    it('stays quiet below the threshold', function () {
        foreach (range(1, 9) as $ignored) {
            failedLogin();
        }

        Http::assertNothingSent();
    });

    it('alerts sooner when the failures target an admin account', function () {
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'boss@example.com']);

        foreach (range(1, 3) as $ignored) {
            failedLogin($admin->email);
        }

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => str_contains($request['text'], 'admin account'));
    });

    it('does not treat three failures against a customer as an attack on an admin', function () {
        User::factory()->create(['role' => 'customer', 'email' => 'customer@example.com']);

        foreach (range(1, 3) as $ignored) {
            failedLogin('customer@example.com');
        }

        Http::assertNothingSent();
    });
});

it('alerts when one address keeps running into rate limits', function () {
    Route::middleware('web')->get('/_abuse-test', fn () => 'ok')->middleware('throttle:1,1');

    foreach (range(1, 32) as $ignored) {
        $this->get('/_abuse-test');
    }

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => str_contains($request['text'], 'rate limits'));
});

it('alerts on a burst of failed payments', function () {
    $listener = app(StripeWebhookListener::class);

    foreach (range(1, 5) as $i) {
        $listener->handle(new WebhookReceived(['id' => "evt_{$i}", 'type' => 'payment_intent.payment_failed', 'data' => ['object' => []]]));
    }

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => str_contains($request['text'], 'card testing'));
});

it('alerts at once when a Stripe webhook fails signature verification', function () {
    config(['cashier.webhook.secret' => 'whsec_test']);

    $this->postJson('/stripe/webhook', ['type' => 'checkout.session.completed'])->assertForbidden();

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => str_contains($request['text'], 'Stripe webhook was rejected'));
});
