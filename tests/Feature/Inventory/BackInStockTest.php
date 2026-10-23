<?php

use App\Events\VariantRestocked;
use App\Listeners\QueueRestockNotifications;
use App\Mail\BackInStock;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RestockNotification;
use App\Models\User;
use App\Models\Wishlist;
use App\Services\StockService;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CatalogFixtures;

/*
 * Back-in-stock emails: a variant that becomes sellable again (stock <= 0 -> > 0 while active, or activated with
 * stock) is announced once (VariantRestocked, after commit) and the users who have it in their cart, or its product in
 * their wishlist while nothing else of the product was buyable, get one queued email.
 */

/** @return array{0: Product, 1: ProductVariant} a product whose default variant "Black" holds $stock units */
function restockProduct(int $stock = 0, string $variantName = 'Black'): array
{
    $product = CatalogFixtures::product(['name' => 'Supermoto LED headlight']);
    $variant = $product->defaultVariant;
    $variant->update(['stock_quantity' => $stock, 'images' => [], 'name' => $variantName]);
    $product->syncStockMirror();

    return [$product->fresh(), $variant->fresh()];
}

function restockCartOf(User $user, ProductVariant $variant): void
{
    $cart = Cart::create(['user_id' => $user->id]);
    CartItem::create([
        'cart_id' => $cart->id,
        'product_id' => $variant->product_id,
        'product_variant_id' => $variant->id,
        'quantity' => 1,
        'price' => $variant->price,
    ]);
}

function restockWishlistOf(User $user, Product $product): void
{
    Wishlist::create(['user_id' => $user->id, 'product_id' => $product->id]);
}

/** Restock through the admin's manual correction path (the product edit form applies a delta). */
function restockBy(ProductVariant $variant, int $delta): void
{
    app(StockService::class)->adjustVariantStock($variant->id, $delta);
}

/** A paid order for $quantity units of $variant whose stock is deducted. */
function restockSell(ProductVariant $variant, int $quantity): Order
{
    $order = Order::factory()->create(['status' => 'processing']);
    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $variant->product_id,
        'product_variant_id' => $variant->id,
        'product_name' => 'Sold item',
        'quantity' => $quantity,
        'price' => 10,
        'total' => 10 * $quantity,
    ]);
    app(StockService::class)->deductForOrder($order);

    return $order->fresh();
}

/** Edit-form payload for one existing variant (the shape Admin/Products/Edit.tsx posts). */
function restockAdminPayload(Product $product, array $rows): array
{
    return [
        'name' => $product->name,
        'category_id' => (string) $product->category_id,
        'size_id' => (string) $product->size_id,
        'status' => 'active',
        'featured' => '0',
        'base_sku' => $product->base_sku,
        'variants' => json_encode(array_map(fn (array $row) => [
            'id' => $row[0]->id,
            'name' => $row[0]->name,
            'sku_suffix' => $row[0]->sku_suffix,
            'price' => (string) $row[0]->price,
            'stock_quantity' => (string) $row[2],
            'stock_quantity_original' => $row[1],
            'description' => '',
            'is_default' => (bool) $row[0]->is_default,
            'sort_order' => $row[0]->sort_order,
            'existing_images' => [],
        ], $rows)),
    ];
}

function restockAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

// ------------------------------------------------------------------ detection: every stock-increase path

describe('detection', function () {
    beforeEach(fn () => Queue::fake());

    it('announces a variant that the admin product form brings from 0 to 5, once', function () {
        [$product, $variant] = restockProduct(0);
        Event::fake([VariantRestocked::class]);

        $this->actingAs(restockAdmin())
            ->put(route('admin.products.update', $product), restockAdminPayload($product, [[$variant, 0, 5]]))
            ->assertSessionHasNoErrors();

        Event::assertDispatchedTimes(VariantRestocked::class, 1);
        Event::assertDispatched(VariantRestocked::class, fn (VariantRestocked $e) => $e->variantId === $variant->id);
    });

    it('does not announce a variant that goes from 3 to 5 on the admin form', function () {
        [$product, $variant] = restockProduct(3);
        Event::fake([VariantRestocked::class]);

        $this->actingAs(restockAdmin())
            ->put(route('admin.products.update', $product), restockAdminPayload($product, [[$variant, 3, 5]]))
            ->assertSessionHasNoErrors();

        Event::assertNotDispatched(VariantRestocked::class);
    });

    it('announces an inactive variant with stock that the admin form activates', function () {
        [$product, $variant] = restockProduct(4);
        $variant->update(['status' => 'inactive']);
        Event::fake([VariantRestocked::class]);

        // The edit form saves every listed variant as active
        $this->actingAs(restockAdmin())
            ->put(route('admin.products.update', $product), restockAdminPayload($product, [[$variant, 4, 4]]))
            ->assertSessionHasNoErrors();

        Event::assertDispatchedTimes(VariantRestocked::class, 1);
    });

    it('announces the quick +1 stock action from 0 and not from 3', function () {
        [$empty] = restockProduct(0);
        [$stocked] = restockProduct(3);
        Event::fake([VariantRestocked::class]);
        $admin = restockAdmin();

        $this->actingAs($admin)->patch(route('admin.products.quick-stock', $empty), ['increment' => true]);
        $this->actingAs($admin)->patch(route('admin.products.quick-stock', $stocked), ['increment' => true]);

        Event::assertDispatchedTimes(VariantRestocked::class, 1);
        Event::assertDispatched(VariantRestocked::class, fn (VariantRestocked $e) => $e->variantId === $empty->defaultVariant->id);
    });

    it('announces stock returned by a cancelled order when it was sold out', function () {
        [, $variant] = restockProduct(2);
        $order = restockSell($variant, 2);
        expect($variant->fresh()->stock_quantity)->toBe(0);
        Event::fake([VariantRestocked::class]);

        $order->update(['status' => 'cancelled']);

        expect($variant->fresh()->stock_quantity)->toBe(2);
        Event::assertDispatchedTimes(VariantRestocked::class, 1);
    });

    it('does not announce stock returned by a cancelled order when units were left', function () {
        [, $variant] = restockProduct(5);
        $order = restockSell($variant, 2);
        Event::fake([VariantRestocked::class]);

        $order->update(['status' => 'cancelled']);

        Event::assertNotDispatched(VariantRestocked::class);
    });

    it('announces a variant saved through Eloquent by any other code path', function () {
        [, $variant] = restockProduct(0);
        Event::fake([VariantRestocked::class]);

        $variant->update(['stock_quantity' => 7]);
        $variant->update(['stock_quantity' => 9]);

        Event::assertDispatchedTimes(VariantRestocked::class, 1);
    });

    it('does not announce a restock whose transaction is rolled back', function () {
        [, $variant] = restockProduct(0);
        Event::fake([VariantRestocked::class]);

        try {
            DB::transaction(function () use ($variant) {
                restockBy($variant, 5);
                throw new RuntimeException('rolled back');
            });
        } catch (RuntimeException) {
        }

        Event::assertNotDispatched(VariantRestocked::class);
    });

    it('lets wishlists through only for the first variant of a product that becomes buyable', function () {
        [$product, $first] = restockProduct(0);
        $second = CatalogFixtures::variant($product, ['name' => 'Red', 'stock_quantity' => 0, 'is_default' => false, 'sort_order' => 1]);
        Event::fake([VariantRestocked::class]);

        $this->actingAs(restockAdmin())
            ->put(route('admin.products.update', $product), restockAdminPayload($product, [[$first, 0, 2], [$second, 0, 2]]))
            ->assertSessionHasNoErrors();

        Event::assertDispatchedTimes(VariantRestocked::class, 2);
        Event::assertDispatched(VariantRestocked::class, fn (VariantRestocked $e) => $e->variantId === $first->id && $e->notifyWishlists);
        Event::assertDispatched(VariantRestocked::class, fn (VariantRestocked $e) => $e->variantId === $second->id && ! $e->notifyWishlists);
    });
});

// ------------------------------------------------------------------ recipients (sync queue, faked mail)

describe('recipients', function () {
    beforeEach(fn () => Mail::fake());

    it('emails the users who have the restocked variant in their cart', function () {
        [$product, $variant] = restockProduct(0);
        $other = CatalogFixtures::variant($product, ['name' => 'Red', 'stock_quantity' => 0, 'is_default' => false]);
        $buyer = User::factory()->create();
        $otherBuyer = User::factory()->create();
        restockCartOf($buyer, $variant);
        restockCartOf($otherBuyer, $other);
        Cart::create(['session_id' => 'guest-session']); // guest carts have no address to write to

        restockBy($variant, 3);

        Mail::assertSent(BackInStock::class, fn (BackInStock $mail) => $mail->hasTo($buyer->email) && $mail->source === RestockNotification::SOURCE_CART);
        Mail::assertNotSent(BackInStock::class, fn (BackInStock $mail) => $mail->hasTo($otherBuyer->email));
        Mail::assertSentCount(1);
        expect(RestockNotification::where('user_id', $buyer->id)->where('product_variant_id', $variant->id)->whereNotNull('sent_at')->count())->toBe(1);
    });

    it('emails the users who wishlisted a product that had nothing to buy', function () {
        [$product, $variant] = restockProduct(0);
        $fan = User::factory()->create();
        restockWishlistOf($fan, $product);

        restockBy($variant, 3);

        Mail::assertSent(BackInStock::class, fn (BackInStock $mail) => $mail->hasTo($fan->email) && $mail->source === RestockNotification::SOURCE_WISHLIST);
    });

    it('does not email wishlists when another variant of the product was already in stock', function () {
        [$product, $variant] = restockProduct(0);
        CatalogFixtures::variant($product, ['name' => 'Red', 'stock_quantity' => 4, 'is_default' => false]);
        $fan = User::factory()->create();
        $buyer = User::factory()->create();
        restockWishlistOf($fan, $product);
        restockCartOf($buyer, $variant);

        restockBy($variant, 3);

        Mail::assertNotSent(BackInStock::class, fn (BackInStock $mail) => $mail->hasTo($fan->email));
        Mail::assertSent(BackInStock::class, fn (BackInStock $mail) => $mail->hasTo($buyer->email));
    });

    it('sends one email to a user who has the item in both cart and wishlist', function () {
        [$product, $variant] = restockProduct(0);
        $user = User::factory()->create();
        restockCartOf($user, $variant);
        restockWishlistOf($user, $product);

        restockBy($variant, 3);

        Mail::assertSentCount(1);
        Mail::assertSent(BackInStock::class, fn (BackInStock $mail) => $mail->source === RestockNotification::SOURCE_CART);
    });

    it('skips users who opted out, unverified and inactive accounts', function () {
        [$product, $variant] = restockProduct(0);
        restockCartOf(User::factory()->create(['notify_back_in_stock' => false]), $variant);
        restockWishlistOf(User::factory()->unverified()->create(), $product);
        restockWishlistOf(User::factory()->create(['is_active' => false]), $product);

        restockBy($variant, 3);

        Mail::assertNothingSent();
        expect(RestockNotification::count())->toBe(0);
    });

    it('sends at most one email per user and variant in 7 days', function () {
        [, $variant] = restockProduct(0);
        $buyer = User::factory()->create();
        restockCartOf($buyer, $variant);

        restockBy($variant, 1);
        restockBy($variant, -1);
        $this->travel(6)->days();
        restockBy($variant, 1);

        Mail::assertSentCount(1);

        restockBy($variant, -1);
        $this->travel(2)->days();
        restockBy($variant, 1);

        Mail::assertSentCount(2);
    });

    it('sends nothing while the admin switch is off', function () {
        settings()->set('email.back_in_stock', false);
        [, $variant] = restockProduct(0);
        restockCartOf(User::factory()->create(), $variant);

        restockBy($variant, 3);

        Mail::assertNothingSent();
    });

    it('sends nothing while all emails are off', function () {
        settings()->set('email.enabled', false);
        [, $variant] = restockProduct(0);
        restockCartOf(User::factory()->create(), $variant);

        restockBy($variant, 3);

        Mail::assertNothingSent();
    });

    it('sends nothing when the variant sold out again before the notifications were prepared', function () {
        [, $variant] = restockProduct(0);
        restockCartOf(User::factory()->create(), $variant);
        Queue::fake();

        restockBy($variant, 1);
        restockBy($variant, -1);

        Queue::assertPushed(CallQueuedListener::class, fn (CallQueuedListener $job) => $job->class === QueueRestockNotifications::class);
        app(QueueRestockNotifications::class)->handle(new VariantRestocked($variant->id));

        expect(RestockNotification::count())->toBe(0);
    });
});

// ------------------------------------------------------------------ queueing, email, unsubscribe, preference

it('queues the work instead of sending inside the admin request', function () {
    [$product, $variant] = restockProduct(0);
    restockCartOf(User::factory()->create(), $variant);
    Mail::fake();
    Queue::fake();

    $this->actingAs(restockAdmin())
        ->put(route('admin.products.update', $product), restockAdminPayload($product, [[$variant, 0, 5]]))
        ->assertSessionHasNoErrors();

    Mail::assertNothingSent();
    Mail::assertNothingQueued();
    Queue::assertPushed(CallQueuedListener::class, fn (CallQueuedListener $job) => $job->class === QueueRestockNotifications::class);
});

it('sends the email in the language of the user\'s last order, else the shop default', function () {
    Mail::fake();
    [, $variant] = restockProduct(0);
    $german = User::factory()->create();
    Order::factory()->create(['user_id' => $german->id, 'locale' => 'de']);
    $plain = User::factory()->create();
    restockCartOf($german, $variant);
    restockCartOf($plain, $variant);

    restockBy($variant, 3);

    Mail::assertSent(BackInStock::class, fn (BackInStock $mail) => $mail->hasTo($german->email) && $mail->locale === 'de');
    Mail::assertSent(BackInStock::class, fn (BackInStock $mail) => $mail->hasTo($plain->email) && $mail->locale === config('app.locale'));
});

it('renders the product, variant, price, a link to the preselected variant and a one-click unsubscribe', function () {
    [$product, $variant] = restockProduct(3, 'Matte black');
    $variant->update(['price' => 49.90]);
    $user = User::factory()->create();
    $notification = RestockNotification::create([
        'user_id' => $user->id,
        'product_variant_id' => $variant->id,
        'source' => RestockNotification::SOURCE_WISHLIST,
    ]);

    Mail::to($user->email)->locale('en')->send(new BackInStock($notification));

    $message = app('mail.manager')->mailer()->getSymfonyTransport()->messages()->first()->getOriginalMessage();
    $html = html_entity_decode($message->getHtmlBody());
    $unsubscribe = URL::signedRoute('restock.unsubscribe', ['user' => $user->id]);

    expect($html)->toContain('Supermoto LED headlight')
        ->and($html)->toContain('Matte black')
        ->and($html)->toContain('49.90')
        ->and($html)->toContain(route('products.show', $product->slug).'?variant='.$variant->id)
        ->and($html)->toContain($unsubscribe)
        ->and($message->getTextBody())->toContain($unsubscribe)
        ->and($message->getHeaders()->get('List-Unsubscribe')->getBodyAsString())->toBe('<'.$unsubscribe.'>')
        ->and($message->getHeaders()->get('List-Unsubscribe-Post')->getBodyAsString())->toBe('List-Unsubscribe=One-Click');
});

it('points cart notifications to the cart', function () {
    [, $variant] = restockProduct(3);
    $user = User::factory()->create();
    $notification = RestockNotification::create([
        'user_id' => $user->id,
        'product_variant_id' => $variant->id,
        'source' => RestockNotification::SOURCE_CART,
    ]);

    expect((new BackInStock($notification))->locale('en')->render())->toContain(route('cart'));
});

it('only asks for confirmation when the signed link is opened (link scanners prefetch GETs)', function () {
    $user = User::factory()->create();
    $url = URL::signedRoute('restock.unsubscribe', ['user' => $user->id]);

    $this->get($url)
        ->assertOk()
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertInertia(fn (Assert $page) => $page->component('Restock/Unsubscribed')
            ->where('status', 'confirm')
            ->where('actionUrl', $url));

    expect($user->fresh()->notify_back_in_stock)->toBeTrue();
});

it('unsubscribes when the confirmation button posts to the signed link, without logging in', function () {
    $user = User::factory()->create();

    $this->withHeaders(['X-Inertia' => 'true'])
        ->post(URL::signedRoute('restock.unsubscribe', ['user' => $user->id]))
        ->assertOk()
        ->assertJsonPath('component', 'Restock/Unsubscribed')
        ->assertJsonPath('props.status', 'done');

    expect($user->fresh()->notify_back_in_stock)->toBeFalse();
});

it('accepts the one-click POST from mail clients without a CSRF token', function () {
    $user = User::factory()->create();

    $this->post(URL::signedRoute('restock.unsubscribe', ['user' => $user->id]), ['List-Unsubscribe' => 'One-Click'])->assertOk();

    expect($user->fresh()->notify_back_in_stock)->toBeFalse()
        ->and(app(PreventRequestForgery::class)->getExcludedPaths())->toContain('restock/unsubscribe/*');
});

it('refuses an unsigned or tampered unsubscribe link', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->get(route('restock.unsubscribe', ['user' => $user->id]))
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page->component('Restock/Unsubscribed')->where('status', 'invalid'));
    $this->post(route('restock.unsubscribe', ['user' => $user->id]))->assertForbidden();
    // per-request header: withHeaders() would carry X-Inertia into the requests below (409 on an asset-version mismatch)
    $this->post(route('restock.unsubscribe', ['user' => $user->id]), [], ['X-Inertia' => 'true'])->assertForbidden();
    $tampered = str_replace('/unsubscribe/'.$user->id, '/unsubscribe/'.$other->id, URL::signedRoute('restock.unsubscribe', ['user' => $user->id]));
    $this->get($tampered)->assertForbidden();
    $this->post($tampered)->assertForbidden();

    expect($user->fresh()->notify_back_in_stock)->toBeTrue()
        ->and($other->fresh()->notify_back_in_stock)->toBeTrue();
});

it('opts new users in and lets them switch it in their account settings', function () {
    $user = User::factory()->create();
    expect($user->fresh()->notify_back_in_stock)->toBeTrue();

    // A real request loads the user from the database (the factory instance lacks the column default)
    $this->actingAs($user->fresh())->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.user.notify_back_in_stock', true));

    $this->actingAs($user)
        ->patch(route('notification-preferences.update'), ['notify_back_in_stock' => false])
        ->assertRedirect(route('profile.edit'));
    expect($user->fresh()->notify_back_in_stock)->toBeFalse();

    $this->actingAs($user)
        ->patch(route('notification-preferences.update'), ['notify_back_in_stock' => true]);
    expect($user->fresh()->notify_back_in_stock)->toBeTrue();
});

it('requires a login to change the preference', function () {
    $this->patch(route('notification-preferences.update'), ['notify_back_in_stock' => false])->assertRedirect(route('login'));
});

it('has the admin switch with label and help in every locale', function () {
    expect(settings('email.back_in_stock'))->toBeTrue();

    foreach (['en', 'it', 'de', 'fr', 'es'] as $locale) {
        $strings = json_decode(file_get_contents(lang_path("{$locale}.json")), true);

        expect($strings['settings.email.back_in_stock.label'] ?? '')->not->toBe('', "{$locale} label")
            ->and($strings['settings.email.back_in_stock.help'] ?? '')->not->toBe('', "{$locale} help")
            ->and($strings['restock']['preference_label'] ?? '')->not->toBe('', "{$locale} account switch")
            ->and($strings['restock']['confirm_button'] ?? '')->not->toBe('', "{$locale} unsubscribe button");
    }
});
