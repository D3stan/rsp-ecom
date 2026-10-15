<?php

use App\Models\Cart;
use App\Models\PendingUserVerification;
use App\Models\User;
use App\Settings\Settings;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Tests\Support\CatalogFixtures;

/*
 * P0-4: a guest cart (keyed by the session id) must survive every way of signing in. Each test builds the guest cart
 * through the real cart endpoint, with the guest's session cookie, and then signs in with that same cookie, exactly
 * as the browser does.
 */

beforeEach(function () {
    Queue::fake();
    Notification::fake();

    // The guest's browser session: the cookie goes with every request, JSON ones included (withCredentials)
    $this->guestSid = Str::random(40);
    $this->withCookie(config('session.cookie'), $this->guestSid)->withCredentials();

    $this->productX = CatalogFixtures::product(['stock_quantity' => 50]);
    $this->variantX = CatalogFixtures::variant($this->productX, ['stock_quantity' => 50, 'price' => 10]);
    $this->productY = CatalogFixtures::product(['stock_quantity' => 50]);
    $this->variantY = CatalogFixtures::variant($this->productY, ['stock_quantity' => 50, 'price' => 20]);
});

function guestCartAdd($test, $product, $variant, int $quantity): void
{
    $test->postJson(route('cart.add'), [
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'quantity' => $quantity,
    ])->assertOk();

    // The line really is in this browser's guest cart
    expect(Cart::where('session_id', $test->guestSid)->whereNull('user_id')->firstOrFail()
        ->cartItems()->where('product_variant_id', $variant->id)->exists())->toBeTrue();
}

function userCartWith(User $user, array $lines): Cart
{
    $cart = Cart::create(['user_id' => $user->id]);
    foreach ($lines as [$product, $variant, $quantity]) {
        $cart->cartItems()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => $quantity,
            'price' => $variant->price,
            'size_id' => $product->size_id,
        ]);
    }

    return $cart;
}

/** variant id => quantity, as the cart page shows it to the (now signed-in) customer. */
function cartPageQuantities($test): array
{
    $quantities = [];
    $test->get(route('cart'))->assertOk()->assertInertia(function ($page) use (&$quantities) {
        foreach ($page->toArray()['props']['cartItems'] as $item) {
            $quantities[$item['variant']['id']] = $item['quantity'];
        }
    });
    ksort($quantities);

    return $quantities;
}

function guestCartRowExists(string $sid): bool
{
    return Cart::where('session_id', $sid)->whereNull('user_id')->exists();
}

function mockGoogleUser(string $id, string $email): void
{
    $googleUser = Mockery::mock('Laravel\Socialite\Two\User');
    $googleUser->shouldReceive('getId')->andReturn($id);
    $googleUser->shouldReceive('getName')->andReturn('Google Person');
    $googleUser->shouldReceive('getEmail')->andReturn($email);
    $googleUser->shouldReceive('getAvatar')->andReturn('https://example.com/avatar.jpg');

    $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
    $provider->shouldReceive('user')->andReturn($googleUser);

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
}

it('documents why the merge cannot read Session::getId() inside the Login event: the id is already rotated', function () {
    // SessionGuard::login() calls updateSession() (session()->migrate(true)) BEFORE it fires Login, so the guest id
    // has to be captured earlier in the request.
    $user = User::factory()->create();
    $seen = $arrivedWith = null;
    Event::listen(Login::class, function () use (&$seen, &$arrivedWith) {
        $seen = Session::getId();
        $arrivedWith = request()->cookies->get(config('session.cookie'));
    });

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    expect($arrivedWith)->toBe($this->guestSid)
        ->and($seen)->not->toBeNull()->not->toBe($this->guestSid);
});

it('merges the guest cart into the existing user cart on login, summing matching lines', function () {
    guestCartAdd($this, $this->productX, $this->variantX, 2);
    $user = User::factory()->create();
    userCartWith($user, [[$this->productX, $this->variantX, 1], [$this->productY, $this->variantY, 1]]);

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();
    $this->assertAuthenticatedAs($user);

    expect(cartPageQuantities($this))->toBe([$this->variantX->id => 3, $this->variantY->id => 1])
        ->and(guestCartRowExists($this->guestSid))->toBeFalse();
});

it('moves the guest items to a user that had no cart', function () {
    guestCartAdd($this, $this->productX, $this->variantX, 2);
    guestCartAdd($this, $this->productY, $this->variantY, 1);
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();

    expect(cartPageQuantities($this))->toBe([$this->variantX->id => 2, $this->variantY->id => 1])
        ->and(guestCartRowExists($this->guestSid))->toBeFalse();
});

it('keeps the guest cart through registration and email verification', function () {
    guestCartAdd($this, $this->productX, $this->variantX, 2);

    $this->post('/register', [
        'name' => 'New Buyer',
        'email' => 'new-buyer@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('verification.pending'));

    $pending = PendingUserVerification::where('email', 'new-buyer@example.com')->firstOrFail();
    $this->get(URL::temporarySignedRoute('verification.verify.pending', now()->addHour(), [
        'token' => $pending->verification_token,
        'email' => $pending->email,
    ]))->assertRedirect();

    $this->assertAuthenticated();
    expect(cartPageQuantities($this))->toBe([$this->variantX->id => 2])
        ->and(guestCartRowExists($this->guestSid))->toBeFalse();
});

it('keeps the guest cart when an existing Google user signs in', function () {
    guestCartAdd($this, $this->productX, $this->variantX, 2);
    $user = User::factory()->create(['google_id' => 'g-123', 'email' => 'g@example.com']);
    userCartWith($user, [[$this->productY, $this->variantY, 1]]);
    mockGoogleUser('g-123', 'g@example.com');

    $this->get(route('auth.google.callback'))->assertRedirect();
    $this->assertAuthenticatedAs($user);

    expect(cartPageQuantities($this))->toBe([$this->variantX->id => 2, $this->variantY->id => 1])
        ->and(guestCartRowExists($this->guestSid))->toBeFalse();
});

it('keeps the guest cart when a new customer signs up with Google', function () {
    guestCartAdd($this, $this->productX, $this->variantX, 2);
    mockGoogleUser('g-new', 'fresh@example.com');

    $this->get(route('auth.google.callback'))->assertRedirect();
    $this->assertAuthenticated();

    expect(cartPageQuantities($this))->toBe([$this->variantX->id => 2])
        ->and(guestCartRowExists($this->guestSid))->toBeFalse();
});

it('caps the merged quantity at the variant stock', function () {
    $this->variantX->update(['stock_quantity' => 5]);
    guestCartAdd($this, $this->productX, $this->variantX, 3);
    $user = User::factory()->create();
    userCartWith($user, [[$this->productX, $this->variantX, 3]]);

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    expect(cartPageQuantities($this))->toBe([$this->variantX->id => 5]);
});

it('caps the merged quantity at orders.max_qty_per_line', function () {
    app(Settings::class)->set('orders.max_qty_per_line', 4, User::factory()->create(['role' => 'admin']));
    guestCartAdd($this, $this->productX, $this->variantX, 3);
    $user = User::factory()->create();
    userCartWith($user, [[$this->productX, $this->variantX, 3]]);

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    expect(cartPageQuantities($this))->toBe([$this->variantX->id => 4]);
});

it('sends a guest who must sign in to checkout back to the checkout details after login', function () {
    // Guest checkout switched off: the cart's "Sign in to continue" goes through the checkout entry point
    app(Settings::class)->set('orders.guest_checkout_enabled', false, User::factory()->create(['role' => 'admin']));
    guestCartAdd($this, $this->productX, $this->variantX, 1);
    $user = User::factory()->create();

    $this->get(route('checkout'))->assertRedirect(route('guest.checkout.details'));
    $this->get(route('guest.checkout.details'))->assertRedirect(route('login'));

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('checkout.details'));
});

it('returns a guest who signs in from the cart (guest checkout on) to the checkout details', function () {
    // The cart's "Already have an account? Sign in" link targets the auth-protected checkout details page, so the
    // auth middleware records it as the intended URL before showing the login form.
    guestCartAdd($this, $this->productX, $this->variantX, 1);
    $user = User::factory()->create();

    $this->get(route('checkout.details'))->assertRedirect(route('login'));

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('checkout.details'));
});

it('replaces a different variant of the same product with the guest line (one line per product in a cart)', function () {
    $otherVariantX = CatalogFixtures::variant($this->productX, ['stock_quantity' => 50, 'price' => 12, 'is_default' => false]);
    guestCartAdd($this, $this->productX, $otherVariantX, 2);
    $user = User::factory()->create();
    userCartWith($user, [[$this->productX, $this->variantX, 1], [$this->productY, $this->variantY, 1]]);

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    expect(cartPageQuantities($this))->toBe([$this->variantY->id => 1, $otherVariantX->id => 2]);
});

it('does not move a guest line whose variant is out of stock, and never shrinks the user line', function () {
    guestCartAdd($this, $this->productX, $this->variantX, 2);
    guestCartAdd($this, $this->productY, $this->variantY, 1);
    $user = User::factory()->create();
    userCartWith($user, [[$this->productX, $this->variantX, 4]]);
    $this->variantX->update(['stock_quantity' => 3]);
    $this->variantY->update(['stock_quantity' => 0]);

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    expect(cartPageQuantities($this))->toBe([$this->variantX->id => 4])
        ->and(guestCartRowExists($this->guestSid))->toBeFalse();
});

it('still signs the customer in when the merge fails', function () {
    guestCartAdd($this, $this->productX, $this->variantX, 1);
    $user = User::factory()->create();
    $this->mock(\App\Services\CartMerger::class)
        ->shouldReceive('merge')->once()->andThrow(new RuntimeException('boom'));

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();

    $this->assertAuthenticatedAs($user);
});
