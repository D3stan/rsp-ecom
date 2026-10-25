<?php

use App\Enums\OpsEventType;
use App\Models\Cart;
use App\Models\OpsEvent;
use App\Models\User;
use App\Services\Ops\FunnelTracker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CatalogFixtures;

function addToCart($test, ?User $user = null)
{
    $product = CatalogFixtures::product(['stock_quantity' => 40]);
    $variant = CatalogFixtures::variant($product, ['stock_quantity' => 20, 'price' => 10]);
    $request = $user ? $test->actingAs($user) : $test;

    return $request->postJson(route('cart.add'), [
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'quantity' => 1,
    ]);
}

it('records a visitor once per event type and day', function () {
    $request = Request::create('/');
    $request->setLaravelSession(app('session.store'));
    $tracker = app(FunnelTracker::class);

    $tracker->record(OpsEventType::AddToCart, $request);
    $tracker->record(OpsEventType::AddToCart, $request);
    $tracker->record(OpsEventType::CheckoutStarted, $request);

    expect(OpsEvent::where('type', OpsEventType::AddToCart->value)->count())->toBe(1)
        ->and(OpsEvent::where('type', OpsEventType::CheckoutStarted->value)->count())->toBe(1);
});

it('counts the same visitor again the next day', function () {
    $request = Request::create('/');
    $request->setLaravelSession(app('session.store'));
    $tracker = app(FunnelTracker::class);

    $tracker->record(OpsEventType::AddToCart, $request);
    $this->travel(1)->day();
    $tracker->record(OpsEventType::AddToCart, $request);

    expect(OpsEvent::count())->toBe(2);
});

it('stores a salted hash of the visitor, never the session or user id', function () {
    $user = User::factory()->create();
    $request = Request::create('/');
    $request->setUserResolver(fn () => $user);

    app(FunnelTracker::class)->record(OpsEventType::AddToCart, $request);

    // Not the bare hash of the id either: it would be reversible by trying every user id
    expect(OpsEvent::first()->visitor)->toHaveLength(40)->not->toBe(sha1('user:'.$user->id));
});

it('records every Stripe webhook, without a visitor', function () {
    app(FunnelTracker::class)->recordWebhook();
    app(FunnelTracker::class)->recordWebhook();

    expect(OpsEvent::where('type', OpsEventType::StripeWebhook->value)->whereNull('visitor')->count())->toBe(2);
});

it('never fails the request when the event cannot be stored', function () {
    Schema::drop('ops_events');
    Log::spy();

    app(FunnelTracker::class)->recordWebhook();

    Log::shouldHaveReceived('warning')->once();
});

it('records add_to_cart when a product is added to the cart', function () {
    addToCart($this)->assertOk();

    expect(OpsEvent::where('type', OpsEventType::AddToCart->value)->count())->toBe(1);
});

it('does not record add_to_cart when the add is rejected', function () {
    $product = CatalogFixtures::product(['stock_quantity' => 1]);
    $variant = CatalogFixtures::variant($product, ['stock_quantity' => 1]);

    $this->postJson(route('cart.add'), [
        'product_id' => $product->id, 'product_variant_id' => $variant->id, 'quantity' => 5,
    ])->assertStatus(422);

    expect(OpsEvent::count())->toBe(0);
});

it('records checkout_started when a customer opens the checkout details', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    addToCart($this, $customer)->assertOk();

    $this->actingAs($customer)->get(route('checkout.details'))->assertOk();

    expect(OpsEvent::where('type', OpsEventType::CheckoutStarted->value)->count())->toBe(1);
});

it('does not record checkout_started for an empty cart', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    Cart::create(['user_id' => $customer->id]);

    $this->actingAs($customer)->get(route('checkout.details'))->assertRedirect(route('cart'));

    expect(OpsEvent::count())->toBe(0);
});
