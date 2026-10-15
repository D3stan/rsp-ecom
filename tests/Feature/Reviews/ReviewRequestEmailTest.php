<?php

use App\Mail\ReviewRequest;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\OrderEmail;
use App\Models\OrderItem;
use App\Models\Review;
use App\Models\User;
use App\Services\Newsletter\MarketingConsent;
use App\Settings\Settings;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CatalogFixtures;

/*
 * H4: a daily command sends one consented review-request email per eligible order. Eligible = delivered at least
 * reviews.request_delay_days ago, has a user_id, not refunded or cancelled, has at least one product the user has
 * not reviewed, the address has marketing consent, and the setting is on. Deduped through OrderEmail so it is sent
 * at most once per order.
 */

beforeEach(function () {
    Mail::fake();
    enableReviewRequests();
    $this->product = CatalogFixtures::product();
    $this->customer = User::factory()->create(['email' => 'rider@example.com']);
});

function enableReviewRequests(bool $enabled = true, int $delayDays = 10): void
{
    $admin = User::factory()->create(['role' => 'admin']);
    app(Settings::class)->set('reviews.request_enabled', $enabled, $admin);
    app(Settings::class)->set('reviews.request_delay_days', $delayDays, $admin);
}

function confirmConsent(string $email): NewsletterSubscriber
{
    $consent = app(MarketingConsent::class);
    $subscriber = $consent->subscribe($email, 'account', 'en');

    return $consent->confirm($consent->issueConfirmationToken($subscriber->fresh()));
}

function deliveredOrder(User $customer, $product, array $overrides = []): Order
{
    $order = Order::factory()->create(array_merge([
        'user_id' => $customer->id,
        'status' => 'delivered',
        'delivered_at' => now()->subDays(11),
        'payment_status' => 'succeeded',
    ], $overrides));
    OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $product->id]);

    return $order;
}

function runReviewRequests(): void
{
    test()->artisan('reviews:send-requests')->assertSuccessful();
}

it('sends one review request to a consented customer for a delivered order', function () {
    confirmConsent('rider@example.com');
    $order = deliveredOrder($this->customer, $this->product);

    runReviewRequests();

    Mail::assertSent(ReviewRequest::class, 1);
    Mail::assertSent(ReviewRequest::class, fn (ReviewRequest $mail) => $mail->hasTo('rider@example.com'));
    expect(OrderEmail::where('order_id', $order->id)->where('type', OrderEmail::TYPE_REVIEW_REQUEST)->exists())->toBeTrue();
});

it('sends nothing without marketing consent', function () {
    // pending (not confirmed) subscriber
    app(MarketingConsent::class)->subscribe('rider@example.com', 'account', 'en');
    deliveredOrder($this->customer, $this->product);

    runReviewRequests();

    Mail::assertNothingSent();
});

it('sends nothing for a guest order', function () {
    confirmConsent('guest@example.com');
    $order = Order::factory()->create([
        'user_id' => null,
        'guest_email' => 'guest@example.com',
        'status' => 'delivered',
        'delivered_at' => now()->subDays(11),
        'payment_status' => 'succeeded',
    ]);
    OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $this->product->id]);

    runReviewRequests();

    Mail::assertNothingSent();
});

it('sends nothing for a refunded or partially refunded order', function (string $paymentStatus) {
    confirmConsent('rider@example.com');
    deliveredOrder($this->customer, $this->product, ['payment_status' => $paymentStatus]);

    runReviewRequests();

    Mail::assertNothingSent();
})->with(['refunded', 'partially_refunded']);

it('sends nothing for a cancelled order', function () {
    confirmConsent('rider@example.com');
    deliveredOrder($this->customer, $this->product, ['status' => 'cancelled']);

    runReviewRequests();

    Mail::assertNothingSent();
});

it('sends nothing when the customer has already reviewed every product in the order', function () {
    confirmConsent('rider@example.com');
    $order = deliveredOrder($this->customer, $this->product);
    Review::factory()->create(['user_id' => $this->customer->id, 'product_id' => $this->product->id]);

    runReviewRequests();

    Mail::assertNothingSent();
});

it('still sends when the order has at least one not-yet-reviewed product', function () {
    confirmConsent('rider@example.com');
    $reviewed = $this->product;
    $unreviewed = CatalogFixtures::product();
    $order = deliveredOrder($this->customer, $reviewed);
    OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $unreviewed->id]);
    Review::factory()->create(['user_id' => $this->customer->id, 'product_id' => $reviewed->id]);

    runReviewRequests();

    Mail::assertSent(ReviewRequest::class, 1);
});

it('sends nothing while the delivery is more recent than the delay', function () {
    confirmConsent('rider@example.com');
    deliveredOrder($this->customer, $this->product, ['delivered_at' => now()->subDays(3)]);

    runReviewRequests();

    Mail::assertNothingSent();
});

it('sends nothing while the feature is switched off', function () {
    enableReviewRequests(false);
    confirmConsent('rider@example.com');
    deliveredOrder($this->customer, $this->product);

    runReviewRequests();

    Mail::assertNothingSent();
});

it('sends at most one review request per order even if the command runs twice', function () {
    confirmConsent('rider@example.com');
    $order = deliveredOrder($this->customer, $this->product);

    runReviewRequests();
    runReviewRequests();

    Mail::assertSent(ReviewRequest::class, 1);
    expect(OrderEmail::where('order_id', $order->id)->where('type', OrderEmail::TYPE_REVIEW_REQUEST)->count())->toBe(1);
});

it('carries a one-click unsubscribe header and link', function () {
    $subscriber = confirmConsent('rider@example.com');
    $order = deliveredOrder($this->customer, $this->product);

    $mailable = new ReviewRequest($order);
    $headers = $mailable->headers();

    expect($mailable->unsubscribeUrl)->toBe($subscriber->unsubscribeUrl())
        ->and($headers->text)->toHaveKey('List-Unsubscribe')
        ->and($headers->text['List-Unsubscribe-Post'])->toBe('List-Unsubscribe=One-Click');

    $mailable->assertSeeInHtml($subscriber->unsubscribeUrl());
});
