<?php

use App\Models\Cart;
use App\Models\CheckoutSnapshot;
use App\Models\User;
use Tests\Feature\Orders\Support\BuildsStripeOrderFixtures;

uses(BuildsStripeOrderFixtures::class);

/*
 * A coupon is checked when it is applied to the cart; it is checked again right before charging. Without the second
 * check, a code that expired or was deactivated while the customer was checking out was still honoured.
 */

beforeEach(function () {
    $this->setUpOrderFixtures();
    $this->fakeEmailService();
    $this->stripeHttp->responses['/v1/customers'] = ['id' => 'cus_test_1', 'object' => 'customer'];
    $this->stripeHttp->responses['/v1/checkout/sessions'] = [
        'id' => 'cs_test_created', 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.test/c/pay/cs_test_created',
    ];

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $product = $this->makeProduct(100.00);
    Cart::create(['user_id' => $this->user->id])->cartItems()->create([
        'product_id' => $product->id, 'product_variant_id' => $product->defaultVariant->id, 'size_id' => $this->size->id,
        'quantity' => 1, 'price' => 100.00,
    ]);

    $this->coupon = ['code' => 'TEN', 'description' => '10% off', 'discount_type' => 'percentage', 'discount_percentage' => 10, 'discount_amount' => 10];
});

afterEach(function () {
    $this->tearDownOrderFixtures();
});

function checkoutWithCoupon($test)
{
    return $test->withSession(['applied_coupon' => $test->coupon])->actingAs($test->user)->post('/checkout/cart', [
        'email' => 'buyer@example.com',
        'shipping_address' => [
            'first_name' => 'Mario', 'last_name' => 'Rossi', 'address_line_1' => 'Via Roma 10', 'city' => 'Milano',
            'state' => 'MI', 'postal_code' => '20100', 'country' => 'IT', 'phone' => '+39 333 1234567',
        ],
        'order_notes' => '',
        'coupon_code' => 'TEN',
    ]);
}

function stripeSessionWasCreated(array $requests): bool
{
    return collect($requests)->contains(fn ($r) => $r[0] === 'post' && str_ends_with($r[1], '/v1/checkout/sessions'));
}

it('charges with the discount when the coupon is still valid', function () {
    $this->fakePromotionCode('TEN');

    checkoutWithCoupon($this)->assertRedirect('https://checkout.stripe.test/c/pay/cs_test_created');

    expect(stripeSessionWasCreated($this->stripeHttp->requests))->toBeTrue()->and(session('applied_coupon.code'))->toBe('TEN');
});

it('refuses to charge and drops the coupon when it is no longer valid', function (array $coupon, bool $found) {
    $this->fakePromotionCode('TEN', $coupon, $found);

    checkoutWithCoupon($this)->assertSessionHasErrors('coupon');

    expect(stripeSessionWasCreated($this->stripeHttp->requests))->toBeFalse()->and(session()->has('applied_coupon'))->toBeFalse();
})->with([
    'expired' => [['redeem_by' => time() - 60], true],
    'no longer valid' => [['valid' => false], true],
    'used up' => [['max_redemptions' => 5, 'times_redeemed' => 5], true],
    'deactivated' => [[], false],
]);

it('records the coupon the discount came from, not a stale code the form still sends', function () {
    $this->actingAs($this->user)->post('/checkout/cart', [
        'email' => 'buyer@example.com',
        'shipping_address' => [
            'first_name' => 'Mario', 'last_name' => 'Rossi', 'address_line_1' => 'Via Roma 10', 'city' => 'Milano',
            'state' => 'MI', 'postal_code' => '20100', 'country' => 'IT', 'phone' => '+39 333 1234567',
        ],
        'order_notes' => '',
        'coupon_code' => 'TEN',
    ])->assertRedirect();

    $session = collect($this->stripeHttp->requests)->last(fn ($r) => $r[0] === 'post' && str_ends_with($r[1], '/v1/checkout/sessions'));

    expect($session[2]['metadata']['coupon_code'])->toBe('')
        ->and(CheckoutSnapshot::query()->latest('id')->value('coupon_code'))->toBeNull();
});
