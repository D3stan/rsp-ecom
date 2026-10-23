<?php

namespace Tests\Feature\Checkout;

use App\Models\Address;
use App\Models\CheckoutSnapshot;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderCreationService;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Orders\Support\BuildsStripeOrderFixtures;
use Tests\TestCase;

/**
 * The shipping address typed at checkout is kept on the order exactly as entered; when the customer asks for it, it is
 * saved in their address book as the default and prefills the next checkout. A guest who signs up later (verified
 * email) gets their guest orders and the address of the latest one. Address validation (province, phone) and the address
 * book of the settings page.
 */
class SavedAddressTest extends TestCase
{
    use BuildsStripeOrderFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrderFixtures();
        $this->fakeEmailService();
        config(['cashier.secret' => 'sk_test_fake_for_tests']);

        $this->stripeHttp->responses['/v1/customers'] = ['id' => 'cus_test_1', 'object' => 'customer'];
        $this->stripeHttp->responses['/v1/checkout/sessions'] = [
            'id' => 'cs_test_created',
            'object' => 'checkout.session',
            'url' => 'https://checkout.stripe.test/c/pay/cs_test_created',
        ];
    }

    protected function tearDown(): void
    {
        $this->tearDownOrderFixtures();
        parent::tearDown();
    }

    private function form(array $address = [], array $extra = []): array
    {
        return [
            'email' => 'buyer@example.com',
            'shipping_address' => $address + [
                'first_name' => 'Mario', 'last_name' => 'Rossi', 'company' => 'Rossi Srl', 'address_line_1' => 'Via Roma 10',
                'address_line_2' => 'Scala B', 'city' => 'Reggio Emilia', 'state' => 'RE', 'postal_code' => '42121',
                'country' => 'IT', 'phone' => '+39 333 1234567',
            ],
            'save_address' => true,
            'order_notes' => '',
            'coupon_code' => '',
        ] + $extra;
    }

    /** Checkout by $user (or a guest) and the Stripe payment that follows; returns the created order. */
    private function placeOrder(?User $user, array $address = [], string $sessionId = 'cs_test_created', bool $save = true)
    {
        $sid = Str::random(40);

        if ($user) {
            $this->makeCart($user);
            $this->actingAs($user)->post('/checkout/cart', ['save_address' => $save] + $this->form($address))->assertRedirect();
        } else {
            $this->makeCart(guestSessionId: $sid);
            $this->withCookie(config('session.cookie'), $sid)->post('/guest/checkout/cart', $this->form($address))->assertRedirect();
        }

        [$params] = $this->lastSessionRequest();

        return app(OrderCreationService::class)->createFromStripeSession($this->sessionPayload([
            'id' => $sessionId,
            'metadata' => $params['metadata'],
        ]));
    }

    private function lastSessionRequest(): array
    {
        foreach (array_reverse($this->stripeHttp->requests) as [$method, $url, $params]) {
            if ($method === 'post' && str_ends_with($url, '/v1/checkout/sessions')) {
                return [$params];
            }
        }

        $this->fail('No Stripe checkout session was created');
    }

    private function bookEntry(User $user, array $overrides = []): Address
    {
        return $user->addresses()->create($overrides + [
            'type' => 'shipping', 'is_default' => false, 'first_name' => 'Anna', 'last_name' => 'Bianchi', 'address_line_1' => 'Via Dante 1',
            'city' => 'Milano', 'state' => 'MI', 'postal_code' => '20121', 'country' => 'IT', 'phone' => '333 1234567',
        ]);
    }

    // ------------------------------------------------------------ the order keeps what was typed

    public function test_the_order_keeps_the_address_as_entered(): void
    {
        $order = $this->placeOrder(User::factory()->create());

        // company and second line survive (the Stripe metadata string cannot carry them)
        $this->assertSame('Rossi Srl', $order->shippingAddress->company);
        $this->assertSame('Scala B', $order->shippingAddress->address_line_2);
        $this->assertSame('RE', $order->shippingAddress->state);
        $this->assertSame('42121', $order->shippingAddress->postal_code);
    }

    public function test_a_guest_order_saves_no_address_book_entry(): void
    {
        $this->placeOrder(null);

        $this->assertSame(0, Address::whereNotNull('user_id')->count());
        $this->assertSame('Rossi Srl', CheckoutSnapshot::sole()->shipping_address['company']);
    }

    // ------------------------------------------------------------ save at checkout

    public function test_a_ticked_save_box_stores_the_address_as_the_default(): void
    {
        $user = User::factory()->create();

        $order = $this->placeOrder($user);

        $default = $user->getDefaultShippingAddress();
        $this->assertSame('Via Roma 10', $default->address_line_1);
        $this->assertSame('Rossi Srl', $default->company);
        $this->assertNotSame($order->shipping_address_id, $default->id);
    }

    public function test_an_unticked_save_box_stores_nothing(): void
    {
        $user = User::factory()->create();

        $this->placeOrder($user, save: false);

        $this->assertSame(0, $user->addresses()->count());
    }

    public function test_a_new_address_is_added_and_becomes_the_default_while_the_old_one_stays(): void
    {
        $user = User::factory()->create();

        $this->placeOrder($user);
        $this->placeOrder($user, ['address_line_1' => 'Via Milano 3', 'company' => ''], 'cs_test_second');

        $this->assertSame(2, $user->addresses()->shipping()->count());
        $this->assertSame(1, $user->addresses()->shipping()->default()->count());
        $default = $user->getDefaultShippingAddress();
        $this->assertSame('Via Milano 3', $default->address_line_1);
        $this->assertNull($default->company);
    }

    public function test_an_address_the_book_already_has_is_not_duplicated_and_keeps_the_chosen_default(): void
    {
        $user = User::factory()->create();

        $this->placeOrder($user);
        $other = $this->bookEntry($user, ['is_default' => true]);
        $user->addresses()->where('id', '!=', $other->id)->update(['is_default' => false]);

        $this->placeOrder($user, sessionId: 'cs_test_second');

        $this->assertSame(2, $user->addresses()->count());
        $this->assertSame($other->id, $user->getDefaultShippingAddress()->id);
    }

    public function test_the_book_makes_room_by_dropping_the_oldest_non_default_entry(): void
    {
        $user = User::factory()->create();
        foreach (range(1, User::MAX_SAVED_ADDRESSES) as $n) {
            $this->bookEntry($user, ['address_line_1' => "Via {$n}", 'is_default' => $n === 1]);
        }

        $this->placeOrder($user);

        $this->assertSame(User::MAX_SAVED_ADDRESSES, $user->addresses()->count());
        $this->assertFalse($user->addresses()->where('address_line_1', 'Via 2')->exists());
        $this->assertSame('Via Roma 10', $user->getDefaultShippingAddress()->address_line_1);
    }

    // ------------------------------------------------------------ the checkout page

    public function test_the_checkout_page_lists_the_address_book_default_first(): void
    {
        $user = User::factory()->create();
        $this->bookEntry($user, ['address_line_1' => 'Via Vecchia 1']);
        $this->bookEntry($user, ['address_line_1' => 'Via Nuova 2', 'is_default' => true]);
        $this->makeCart($user);

        $this->actingAs($user)->get('/checkout/details')->assertInertia(fn (Assert $page) => $page
            ->component('Checkout/Details')
            ->has('savedAddresses', 2)
            ->where('savedAddresses.0.address_line_1', 'Via Nuova 2')
            ->where('savedAddresses.0.is_default', true)
            ->has('provinces.MI'));
    }

    public function test_a_customer_without_saved_addresses_gets_an_empty_list(): void
    {
        $user = User::factory()->create();
        $this->makeCart($user);

        $this->actingAs($user)->get('/checkout/details')->assertInertia(fn (Assert $page) => $page->where('savedAddresses', []));
    }

    public function test_only_a_signed_in_checkout_offers_to_save_the_card(): void
    {
        $this->placeOrder(User::factory()->create());
        [$params] = $this->lastSessionRequest();
        $this->assertSame('enabled', $params['saved_payment_method_options']['payment_method_save']);

        $this->placeOrder(null, sessionId: 'cs_test_guest');
        [$params] = $this->lastSessionRequest();
        $this->assertArrayNotHasKey('saved_payment_method_options', $params);
    }

    // ------------------------------------------------------------ validation

    public function test_an_italian_state_must_be_a_province_code(): void
    {
        $user = User::factory()->create();
        $this->makeCart($user);

        $this->actingAs($user)->post('/checkout/cart', $this->form(['state' => 'Reggio Emilia']))
            ->assertSessionHasErrors('shipping_address.state');
    }

    public function test_the_phone_must_look_like_a_phone_number(): void
    {
        $user = User::factory()->create();
        $this->makeCart($user);

        $this->actingAs($user)->post('/checkout/cart', $this->form(['phone' => 'call me']))
            ->assertSessionHasErrors('shipping_address.phone');
        $this->actingAs($user)->post('/checkout/cart', $this->form(['phone' => '12345']))
            ->assertSessionHasErrors('shipping_address.phone');
        $this->actingAs($user)->post('/checkout/cart', $this->form(['phone' => '+39 (333) 123-4567']))
            ->assertSessionHasNoErrors();
    }

    // ------------------------------------------------------------ guest orders join the account

    public function test_a_verified_signup_claims_the_guest_orders_and_gets_their_address(): void
    {
        $guestOrder = $this->placeOrder(null);
        $user = User::factory()->create(['email' => 'Buyer@Example.com']);

        $this->assertSame(1, $user->claimGuestOrders());

        $this->assertSame($user->id, $guestOrder->fresh()->user_id);
        $this->assertSame('Rossi Srl', $user->getDefaultShippingAddress()->company);
        $this->assertSame(0, $user->claimGuestOrders(), 'claiming twice changes nothing');
        $this->assertSame(1, $user->addresses()->count());
    }

    public function test_an_unverified_email_claims_nothing(): void
    {
        $guestOrder = $this->placeOrder(null);
        $user = User::factory()->unverified()->create(['email' => 'buyer@example.com']);

        $this->assertSame(0, $user->claimGuestOrders());
        $this->assertNull($guestOrder->fresh()->user_id);
        $this->assertSame(0, $user->addresses()->count());
    }

    public function test_the_orders_of_other_emails_and_other_accounts_are_left_alone(): void
    {
        $guestOrder = $this->placeOrder(null);
        $user = User::factory()->create(['email' => 'someone-else@example.com']);
        $owned = $this->placeOrder(User::factory()->create(), sessionId: 'cs_test_owned');

        $this->assertSame(0, $user->claimGuestOrders());
        $this->assertNull($guestOrder->fresh()->user_id);
        $this->assertNotSame($user->id, $owned->fresh()->user_id);
    }

    public function test_signing_in_claims_the_guest_orders(): void
    {
        $guestOrder = $this->placeOrder(null);
        $user = User::factory()->create(['email' => 'buyer@example.com']);

        Event::dispatch(new Login('web', $user, false));

        $this->assertSame($user->id, $guestOrder->fresh()->user_id);
    }

    public function test_a_claimed_order_keeps_working_as_the_customers_order(): void
    {
        $guestOrder = $this->placeOrder(null);
        $user = User::factory()->create(['email' => 'buyer@example.com']);
        $user->claimGuestOrders();

        $this->assertFalse($guestOrder->fresh()->isGuestOrder());
        $this->assertSame('buyer@example.com', $guestOrder->fresh()->getCustomerEmail());
        $this->assertSame(1, Order::where('user_id', $user->id)->count());
    }

    // ------------------------------------------------------------ the address book (settings)

    public function test_the_address_book_page_lists_the_entries(): void
    {
        $user = User::factory()->create();
        $this->bookEntry($user, ['is_default' => true]);
        $this->bookEntry(User::factory()->create());

        // the new page is not in a stale Vite build
        $this->withoutVite()->actingAs($user)->get('/settings/addresses')->assertInertia(fn (Assert $page) => $page
            ->component('settings/addresses')
            ->has('addresses', 1)
            ->where('max', User::MAX_SAVED_ADDRESSES));
    }

    public function test_the_first_address_added_is_the_default_and_a_later_one_can_take_over(): void
    {
        $user = User::factory()->create();
        $entry = fn (array $o = []) => $o + [
            'first_name' => 'Anna', 'last_name' => 'Bianchi', 'address_line_1' => 'Via Dante 1', 'city' => 'Milano', 'state' => 'MI',
            'postal_code' => '20121', 'country' => 'IT', 'phone' => '333 1234567',
        ];

        $this->actingAs($user)->post('/settings/addresses', $entry())->assertRedirect();
        $first = $user->getDefaultShippingAddress();
        $this->assertNotNull($first);

        $this->actingAs($user)->post('/settings/addresses', $entry(['address_line_1' => 'Via Verdi 2']));
        $this->assertSame($first->id, $user->getDefaultShippingAddress()->id);

        $this->actingAs($user)->post('/settings/addresses', $entry(['address_line_1' => 'Via Rossi 3', 'is_default' => true]));
        $this->assertSame('Via Rossi 3', $user->getDefaultShippingAddress()->address_line_1);
        $this->assertSame(1, $user->addresses()->default()->count());
    }

    public function test_an_entry_can_be_edited_and_made_the_default(): void
    {
        $user = User::factory()->create();
        $a = $this->bookEntry($user, ['is_default' => true]);
        $b = $this->bookEntry($user);

        $this->actingAs($user)->put("/settings/addresses/{$b->id}", [
            'first_name' => 'Anna', 'last_name' => 'Bianchi', 'address_line_1' => 'Via Nuova 9', 'city' => 'Torino', 'state' => 'TO',
            'postal_code' => '10100', 'country' => 'IT', 'phone' => '333 1234567', 'is_default' => true,
        ])->assertRedirect();

        $this->assertSame('Via Nuova 9', $b->fresh()->address_line_1);
        $this->assertTrue($b->fresh()->is_default);
        $this->assertFalse($a->fresh()->is_default);
    }

    public function test_deleting_the_default_promotes_another_entry_and_never_touches_orders(): void
    {
        $user = User::factory()->create();
        $order = $this->placeOrder($user);
        $default = $user->getDefaultShippingAddress();
        $other = $this->bookEntry($user);

        $this->actingAs($user)->delete("/settings/addresses/{$default->id}")->assertRedirect();

        $this->assertNull(Address::find($default->id));
        $this->assertTrue($other->fresh()->is_default);
        $this->assertNotNull($order->fresh()->shippingAddress, 'the order keeps its own copy of the address');
    }

    public function test_nobody_can_read_change_or_delete_another_customers_entry(): void
    {
        $owner = User::factory()->create();
        $entry = $this->bookEntry($owner, ['is_default' => true]);
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->put("/settings/addresses/{$entry->id}", [
            'first_name' => 'X', 'last_name' => 'Y', 'address_line_1' => 'Hacked', 'city' => 'Roma', 'state' => 'RM',
            'postal_code' => '00100', 'country' => 'IT',
        ])->assertNotFound();
        $this->actingAs($intruder)->delete("/settings/addresses/{$entry->id}")->assertNotFound();

        $this->assertSame('Via Dante 1', $entry->fresh()->address_line_1);
    }

    public function test_the_address_book_is_capped(): void
    {
        $user = User::factory()->create();
        foreach (range(1, User::MAX_SAVED_ADDRESSES) as $n) {
            $this->bookEntry($user, ['address_line_1' => "Via {$n}", 'is_default' => $n === 1]);
        }

        $this->actingAs($user)->post('/settings/addresses', [
            'first_name' => 'Anna', 'last_name' => 'Bianchi', 'address_line_1' => 'One too many', 'city' => 'Milano', 'state' => 'MI',
            'postal_code' => '20121', 'country' => 'IT',
        ])->assertSessionHasErrors('address_line_1');

        $this->assertSame(User::MAX_SAVED_ADDRESSES, $user->addresses()->count());
    }

    public function test_the_address_book_requires_a_signed_in_customer(): void
    {
        $this->get('/settings/addresses')->assertRedirect('/login');
    }
}
