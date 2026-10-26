<?php

namespace Tests\Feature\Orders;

use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\OrderCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;
use Tests\Feature\Orders\Support\BuildsStripeOrderFixtures;
use Tests\TestCase;

class OrderCreationServiceTest extends TestCase
{
    use BuildsStripeOrderFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrderFixtures();
        $this->fakeEmailService();
    }

    protected function tearDown(): void
    {
        $this->tearDownOrderFixtures();
        parent::tearDown();
    }

    private function service(): OrderCreationService
    {
        return app(OrderCreationService::class);
    }

    public function test_creates_guest_order_with_items_totals_and_cleans_up_cart(): void
    {
        $cart = $this->makeCart(guestSessionId: 'guest-session-1');
        $item = $cart->cartItems->first();

        $order = $this->service()->createFromStripeSession($this->guestSessionPayload($cart));

        $this->assertSame(1, Order::count());
        $this->assertSame('cs_test_123', $order->stripe_checkout_session_id);
        $this->assertSame('pi_test_123', $order->stripe_payment_intent_id);
        $this->assertNull($order->user_id);
        $this->assertSame('buyer@example.com', $order->guest_email);
        $this->assertSame('+39 333 1234567', $order->guest_phone);
        $this->assertSame('guest-session-1', $order->guest_session_id);
        $this->assertSame('processing', $order->status);
        $this->assertSame('succeeded', $order->payment_status);
        $this->assertSame('EUR', $order->currency);
        $this->assertSame('it', $order->locale);
        $this->assertEquals(50.00, $order->subtotal);
        $this->assertEquals(5.00, $order->shipping_amount);
        $this->assertEquals(0, $order->discount_amount);
        $this->assertEquals(9.92, $order->tax_amount); // 22% contained in 55.00
        $this->assertEquals(22.00, $order->tax_rate);
        $this->assertEquals(55.00, $order->total_amount);

        $orderItem = $order->orderItems->first();
        $this->assertSame($item->product_id, $orderItem->product_id);
        $this->assertSame($item->product_variant_id, $orderItem->product_variant_id);
        $this->assertSame($this->size->id, $orderItem->size_id);
        $this->assertSame('Test Product', $orderItem->product_name);
        $this->assertSame(2, $orderItem->quantity);
        $this->assertEquals(25.00, $orderItem->price);
        $this->assertEquals(50.00, $orderItem->total);

        $this->assertSame('Mario', $order->shippingAddress->first_name);
        $this->assertSame('Rossi', $order->shippingAddress->last_name);
        $this->assertSame('Via Roma 10', $order->shippingAddress->address_line_1);
        $this->assertSame('Milano', $order->shippingAddress->city);
        $this->assertSame('MI', $order->shippingAddress->state);
        $this->assertSame('20100', $order->shippingAddress->postal_code);
        $this->assertSame('IT', $order->shippingAddress->country);
        $this->assertSame('billing', $order->billingAddress->type);

        $this->assertNull(Cart::find($cart->id));
        $this->assertSame(0, CartItem::count());
    }

    public function test_creates_registered_user_order_from_cart_id_metadata(): void
    {
        $user = User::factory()->create();
        $cart = $this->makeCart($user);

        $order = $this->service()->createFromStripeSession($this->userSessionPayload($user, $cart));

        $this->assertSame($user->id, $order->user_id);
        $this->assertNull($order->guest_email);
        $this->assertNull($order->guest_session_id);
        $this->assertSame(1, $order->orderItems()->count());
        $this->assertNull(Cart::find($cart->id));
    }

    public function test_accepts_stripe_objects_as_well_as_arrays(): void
    {
        $cart = $this->makeCart(guestSessionId: 'guest-session-obj');
        $payload = $this->guestSessionPayload($cart);

        $session = Session::constructFrom($payload);

        $order = $this->service()->createFromStripeSession($session);

        $this->assertSame('cs_test_123', $order->stripe_checkout_session_id);
        $this->assertEquals(55.00, $order->total_amount);
        $this->assertSame('Mario', $order->shippingAddress->first_name);
    }

    public function test_discount_is_derived_from_paid_total_when_a_coupon_was_used(): void
    {
        $cart = $this->makeCart(guestSessionId: 'guest-session-2');

        // items 50 + shipping 5 - 10% coupon on items (5.00) = 50.00 charged
        $payload = $this->guestSessionPayload($cart, [
            'amount_subtotal' => 5000,
            'amount_total' => 5000,
            'metadata' => ['coupon_code' => 'WELCOME10'],
        ]);

        $order = $this->service()->createFromStripeSession($payload);

        $this->assertSame('WELCOME10', $order->coupon_code);
        $this->assertEquals(5.00, $order->discount_amount);
        $this->assertEquals(50.00, $order->subtotal);
        $this->assertEquals(5.00, $order->shipping_amount);
        $this->assertEquals(50.00, $order->total_amount);
    }

    public function test_stripe_discount_total_wins_over_derived_discount(): void
    {
        $cart = $this->makeCart(guestSessionId: 'guest-session-3');

        $payload = $this->guestSessionPayload($cart, [
            'amount_total' => 4500,
            'total_details' => ['amount_discount' => 1000],
            'metadata' => ['coupon_code' => 'PROMO10'],
        ]);

        $order = $this->service()->createFromStripeSession($payload);

        $this->assertEquals(10.00, $order->discount_amount);
        $this->assertSame('PROMO10', $order->coupon_code);
    }

    public function test_blank_coupon_code_is_stored_as_null_and_no_discount(): void
    {
        $cart = $this->makeCart(guestSessionId: 'guest-session-4');

        $order = $this->service()->createFromStripeSession($this->guestSessionPayload($cart));

        $this->assertNull($order->coupon_code);
        $this->assertEquals(0, $order->discount_amount);
    }

    public function test_one_cent_rounding_difference_is_not_shown_as_a_discount(): void
    {
        $cart = $this->makeCart(guestSessionId: 'guest-session-rounding');

        // Cart is 55.00; a session charged 54.99 because amounts used to be truncated, not rounded.
        $payload = $this->guestSessionPayload($cart, ['amount_total' => 5499]);

        $order = $this->service()->createFromStripeSession($payload);

        $this->assertEquals(0, $order->discount_amount);
        $this->assertEquals(54.99, $order->total_amount);
    }

    public function test_vat_is_the_portion_of_the_paid_total_at_the_current_rate_and_ignores_stripes_tax(): void
    {
        $cart = $this->makeCart(guestSessionId: 'guest-session-5');

        // Stripe's own tax figure (990) is not used: the charge is one VAT-inclusive amount
        $payload = $this->guestSessionPayload($cart, ['total_details' => ['amount_tax' => 990]]);

        $order = $this->service()->createFromStripeSession($payload);

        $this->assertEquals(9.92, $order->tax_amount);
        $this->assertEquals(22.00, $order->tax_rate);
    }

    public function test_vat_of_a_discounted_order_is_taken_from_what_was_paid(): void
    {
        $cart = $this->makeCart(guestSessionId: 'guest-session-vat-discount');

        // items 50.00 + shipping 5.00 - 10.00 discount = 45.00 paid; 22% contained = 8.11
        $payload = $this->guestSessionPayload($cart, [
            'amount_total' => 4500,
            'total_details' => ['amount_discount' => 1000],
        ]);

        $order = $this->service()->createFromStripeSession($payload);

        $this->assertEquals(45.00, $order->total_amount);
        $this->assertEquals(8.11, $order->tax_amount);
    }

    public function test_the_rate_is_a_snapshot_and_follows_the_setting_at_the_time_of_the_order(): void
    {
        settings()->set('tax.rate', 10);
        $cart = $this->makeCart(guestSessionId: 'guest-session-vat-rate');

        $order = $this->service()->createFromStripeSession($this->guestSessionPayload($cart));
        settings()->set('tax.rate', 22);

        $this->assertEquals(5.00, $order->fresh()->tax_amount); // 10% contained in 55.00
        $this->assertEquals(10.00, $order->fresh()->tax_rate);
    }

    public function test_without_vat_inclusive_prices_no_vat_is_recorded(): void
    {
        settings()->set('tax.prices_include_tax', false);
        $cart = $this->makeCart(guestSessionId: 'guest-session-no-vat');

        $order = $this->service()->createFromStripeSession($this->guestSessionPayload($cart));

        $this->assertEquals(0, $order->tax_amount);
        $this->assertNull($order->tax_rate);
        $this->assertEquals(55.00, $order->total_amount);
    }

    public function test_same_session_twice_returns_the_same_order_without_duplicates(): void
    {
        $cart = $this->makeCart(guestSessionId: 'guest-session-6');
        $payload = $this->guestSessionPayload($cart);

        $first = $this->service()->createFromStripeSession($payload);
        $addressCount = Address::count();
        $second = $this->service()->createFromStripeSession($payload);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Order::count());
        $this->assertSame(1, OrderItem::count());
        $this->assertSame($addressCount, Address::count());
    }

    public function test_confirmation_is_requested_after_the_transaction_commits(): void
    {
        $cart = $this->makeCart(guestSessionId: 'guest-session-7');
        $baseline = DB::transactionLevel();
        $seen = [];

        $this->fakeEmailService(function ($order) use (&$seen) {
            $seen = [
                'level' => DB::transactionLevel(),
                'persisted' => Order::whereKey($order->id)->exists(),
                'items' => OrderItem::where('order_id', $order->id)->count(),
            ];

            return true;
        });

        $order = $this->service()->createFromStripeSession($this->guestSessionPayload($cart));

        $this->assertSame([$order->id], $this->confirmationCalls);
        $this->assertSame($baseline, $seen['level']);
        $this->assertTrue($seen['persisted']);
        $this->assertSame(1, $seen['items']);
    }

    public function test_confirmation_is_not_requested_for_unpaid_sessions(): void
    {
        $cart = $this->makeCart(guestSessionId: 'guest-session-8');

        $order = $this->service()->createFromStripeSession(
            $this->guestSessionPayload($cart, ['payment_status' => 'unpaid'])
        );

        $this->assertSame('pending', $order->status);
        $this->assertSame('processing', $order->payment_status);
        $this->assertSame([], $this->confirmationCalls);
        // an unpaid session must not wipe the customer's cart
        $this->assertNotNull(Cart::find($cart->id));
    }

    public function test_paid_replay_upgrades_an_unpaid_order_and_cleans_the_cart_once(): void
    {
        $cart = $this->makeCart(guestSessionId: 'guest-session-9');

        $unpaid = $this->service()->createFromStripeSession(
            $this->guestSessionPayload($cart, ['payment_status' => 'unpaid'])
        );
        $paid = $this->service()->createFromStripeSession($this->guestSessionPayload($cart));

        $this->assertSame($unpaid->id, $paid->id);
        $this->assertSame(1, Order::count());
        $this->assertSame('succeeded', $paid->payment_status);
        $this->assertSame('processing', $paid->status);
        $this->assertSame('pi_test_123', $paid->stripe_payment_intent_id);
        $this->assertSame([$paid->id], $this->confirmationCalls);
        $this->assertNull(Cart::find($cart->id));
    }

    public function test_locale_is_validated_against_supported_locales(): void
    {
        foreach (['de' => 'de', 'FR' => 'fr', 'es-ES' => 'es', 'en' => 'en', 'xx' => 'it', '' => 'it', "'; drop" => 'it'] as $input => $expected) {
            $cart = $this->makeCart(guestSessionId: "guest-locale-{$expected}-".md5((string) $input));
            $order = $this->service()->createFromStripeSession($this->guestSessionPayload($cart, [
                'id' => 'cs_test_locale_'.md5((string) $input),
                'metadata' => ['locale' => (string) $input],
            ]));

            $this->assertSame($expected, $order->locale, "locale '{$input}'");
        }
    }

    public function test_missing_locale_metadata_falls_back_to_italian(): void
    {
        $cart = $this->makeCart(guestSessionId: 'guest-session-11');
        $payload = $this->guestSessionPayload($cart);
        unset($payload['metadata']['locale']);

        $order = $this->service()->createFromStripeSession($payload);

        $this->assertSame('it', $order->locale);
    }

    public function test_missing_cart_still_creates_the_paid_order_from_the_session(): void
    {
        $payload = $this->sessionPayload([
            'metadata' => [
                'guest_session_id' => 'cart-is-gone',
                'description' => '2x Test Product (Medium)',
            ],
        ]);

        $order = $this->service()->createFromStripeSession($payload);

        $this->assertSame(1, Order::count());
        $this->assertEquals(55.00, $order->total_amount);
        $this->assertSame(1, $order->orderItems()->count());
        $this->assertStringContainsString('2x Test Product', $order->orderItems->first()->product_name);
    }

    public function test_user_id_of_a_deleted_user_falls_back_to_a_guest_order(): void
    {
        $cart = $this->makeCart(guestSessionId: 'guest-session-12');

        $order = $this->service()->createFromStripeSession($this->guestSessionPayload($cart, [
            'metadata' => ['user_id' => '999999'],
        ]));

        $this->assertNull($order->user_id);
        $this->assertSame('buyer@example.com', $order->guest_email);
    }

    public function test_lost_race_on_the_unique_index_resolves_to_the_existing_order(): void
    {
        $cart = $this->makeCart(guestSessionId: 'guest-session-13');
        $payload = $this->guestSessionPayload($cart);

        $winner = $this->service()->createFromStripeSession($payload);
        $this->assertSame(1, Order::count());

        // A second worker that did not see the winner's row yet: its first lookup misses,
        // the INSERT hits the unique index, and it must resolve to the existing order.
        $racing = new class extends OrderCreationService
        {
            public int $lookups = 0;

            protected function lockedLookup(string $sessionId): ?Order
            {
                return $this->lookups++ === 0 ? null : parent::lockedLookup($sessionId);
            }
        };

        $cart2 = $this->makeCart(guestSessionId: 'guest-session-13');
        $order = $racing->createFromStripeSession($this->guestSessionPayload($cart2));

        $this->assertSame($winner->id, $order->id);
        $this->assertSame(1, Order::count());
        $this->assertSame(1, OrderItem::count());
    }

    public function test_failure_rolls_everything_back_and_keeps_the_cart(): void
    {
        $cart = $this->makeCart(guestSessionId: 'guest-session-14');

        OrderItem::creating(function () {
            throw new \RuntimeException('boom while saving items');
        });

        try {
            $this->service()->createFromStripeSession($this->guestSessionPayload($cart));
            $this->fail('Expected the exception to propagate');
        } catch (\RuntimeException $e) {
            $this->assertSame('boom while saving items', $e->getMessage());
        } finally {
            OrderItem::flushEventListeners();
        }

        $this->assertSame(0, Order::count());
        $this->assertSame(0, Address::count());
        $this->assertNotNull(Cart::find($cart->id));
        $this->assertSame([], $this->confirmationCalls);
    }

    public function test_email_failure_does_not_fail_order_creation(): void
    {
        $cart = $this->makeCart(guestSessionId: 'guest-session-15');
        $this->fakeEmailService(fn () => throw new \RuntimeException('mail down'));

        $order = $this->service()->createFromStripeSession($this->guestSessionPayload($cart));

        $this->assertSame(1, Order::count());
        $this->assertSame('succeeded', $order->payment_status);
    }

    public function test_session_without_id_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service()->createFromStripeSession(['payment_status' => 'paid']);
    }

    public function test_foreign_session_without_shop_metadata_creates_nothing(): void
    {
        $payload = $this->sessionPayload(); // no cart_id / guest_session_id / user_id / source

        $order = $this->service()->createFromStripeSession($payload);

        $this->assertNull($order);
        $this->assertSame(0, Order::count());
        $this->assertSame(0, Address::count());
        $this->assertSame([], $this->confirmationCalls);
    }

    public function test_session_with_the_shop_marker_is_accepted(): void
    {
        $payload = $this->sessionPayload(['metadata' => ['source' => 'rsp-ecom']]);

        $order = $this->service()->createFromStripeSession($payload);

        $this->assertNotNull($order);
        $this->assertSame(1, Order::count());
    }

    public function test_session_with_a_wrong_marker_and_no_legacy_keys_is_rejected(): void
    {
        $payload = $this->sessionPayload(['metadata' => ['source' => 'other-shop']]);

        $this->assertNull($this->service()->createFromStripeSession($payload));
        $this->assertSame(0, Order::count());
    }

    public function test_an_already_known_order_is_returned_even_if_its_session_has_no_marker(): void
    {
        $cart = $this->makeCart(guestSessionId: 'guest-known');
        $created = $this->service()->createFromStripeSession($this->guestSessionPayload($cart));

        $metadataStripped = $this->sessionPayload();
        $again = $this->service()->createFromStripeSession($metadataStripped);

        $this->assertSame($created->id, $again->id);
    }

    public function test_cart_hash_is_stable_and_changes_with_the_content(): void
    {
        $cart = $this->makeCart(guestSessionId: 'guest-hash-0');
        $hash = $cart->contentHash();

        $this->assertSame(40, strlen($hash));
        $this->assertSame($hash, $cart->fresh()->contentHash());

        $cart->cartItems->first()->update(['quantity' => 3]);
        $this->assertNotSame($hash, $cart->fresh()->contentHash());
    }

    public function test_matching_cart_hash_uses_the_cart(): void
    {
        $cart = $this->makeCart(guestSessionId: 'guest-hash-1');
        $payload = $this->guestSessionPayload($cart, ['metadata' => ['cart_hash' => $cart->contentHash()]]);

        $order = $this->service()->createFromStripeSession($payload);

        $this->assertSame('Test Product', $order->orderItems->first()->product_name);
        $this->assertNotNull($order->orderItems->first()->product_variant_id);
        $this->assertNull(Cart::find($cart->id));
    }

    public function test_mutated_cart_is_not_trusted_and_is_left_untouched(): void
    {
        $cart = $this->makeCart(guestSessionId: 'guest-hash-2');
        $payload = $this->guestSessionPayload($cart, [
            'metadata' => ['cart_hash' => $cart->contentHash(), 'description' => '2x Test Product'],
        ]);

        // the customer changes the cart in another tab after starting the checkout
        $cart->cartItems->first()->update(['quantity' => 5]);
        $this->stripeHttp->responses['/line_items'] = [
            'object' => 'list',
            'data' => [['description' => 'Cart Purchase', 'quantity' => 1, 'price' => ['unit_amount' => 5500]]],
        ];

        Log::spy();
        $order = $this->service()->createFromStripeSession($payload);

        $item = $order->orderItems->sole();
        $this->assertNull($item->product_id);
        $this->assertSame('2x Test Product', $item->product_name);
        $this->assertEquals(55.00, $item->total);
        $this->assertEquals(55.00, $order->total_amount);
        $this->assertNotNull(Cart::find($cart->id));
        $this->assertSame(1, $cart->fresh()->cartItems()->count());
        Log::shouldHaveReceived('error')->withArgs(fn ($message, $context = []) => isset($context['expected_cart_hash'], $context['current_cart_hash']));
    }

    public function test_session_without_cart_hash_keeps_the_legacy_behaviour(): void
    {
        $cart = $this->makeCart(guestSessionId: 'guest-hash-3');
        $cart->cartItems->first()->update(['quantity' => 4]);

        $order = $this->service()->createFromStripeSession($this->guestSessionPayload($cart->fresh()));

        $this->assertSame('Test Product', $order->orderItems->first()->product_name);
        $this->assertSame(4, $order->orderItems->first()->quantity);
        $this->assertNull(Cart::find($cart->id));
    }
}
