<?php

use App\Models\Order;
use App\Models\User;
use App\Services\PromotionService;
use Tests\Feature\Orders\Support\BuildsStripeOrderFixtures;

uses(BuildsStripeOrderFixtures::class);

/*
 * Everything an admin can set on a coupon or promotion code in the Stripe Dashboard is either honoured or the code is
 * refused. The shop never passes the code to Stripe, so usage is counted from the shop's own paid orders.
 */

beforeEach(function () {
    $this->setUpOrderFixtures();
});

afterEach(function () {
    $this->tearDownOrderFixtures();
});

function validateCode(?float $itemsTotal = null, ?User $user = null, ?string $email = null): array
{
    return app(PromotionService::class)->validatePromotionCode('TEN', $itemsTotal, $user, $email);
}

function paidOrderWith(?string $code, array $attributes = []): Order
{
    return Order::factory()->create(array_merge(['coupon_code' => $code, 'payment_status' => 'succeeded'], $attributes));
}

it('accepts a plain code', function () {
    $this->fakePromotionCode('TEN');

    expect(validateCode(100)['valid'])->toBeTrue();
});

it('refuses a code whose own expiry has passed', function () {
    $this->fakePromotionCode('TEN', promotion: ['expires_at' => time() - 60]);

    expect(validateCode())->toMatchArray(['valid' => false, 'error' => __('coupon.expired')]);
});

it('counts the shop\'s paid orders toward the promotion code limit, ignoring case', function () {
    $this->fakePromotionCode('TEN', promotion: ['max_redemptions' => 2]);
    paidOrderWith('ten');
    paidOrderWith('TEN', ['payment_status' => 'failed']);

    expect(validateCode()['valid'])->toBeTrue();

    paidOrderWith('Ten', ['payment_status' => 'refunded']);

    expect(validateCode())->toMatchArray(['valid' => false, 'error' => __('coupon.used_up')]);
});

it('adds Stripe\'s own redemptions to the coupon-wide limit', function () {
    $this->fakePromotionCode('TEN', ['max_redemptions' => 3, 'times_redeemed' => 1]);
    paidOrderWith('TEN');

    expect(validateCode()['valid'])->toBeTrue();

    paidOrderWith('TEN');

    expect(validateCode())->toMatchArray(['valid' => false, 'error' => __('coupon.used_up')]);
});

it('refuses codes limited to Stripe customers or products', function (array $coupon, array $promotion) {
    $this->fakePromotionCode('TEN', $coupon, promotion: $promotion);

    expect(validateCode())->toMatchArray(['valid' => false, 'error' => __('coupon.not_available')]);
})->with([
    'one customer' => [[], ['customer' => 'cus_someone']],
    'some products' => [['applies_to' => ['products' => ['prod_x']]], []],
]);

it('honours the minimum amount when the items total is known', function () {
    $this->fakePromotionCode('TEN', promotion: ['restrictions' => ['minimum_amount' => 5000, 'minimum_amount_currency' => 'eur']]);

    expect(validateCode(49.99))->toMatchArray(['valid' => false])
        ->and(validateCode(49.99)['error'])->toContain('50')
        ->and(validateCode(50.00)['valid'])->toBeTrue()
        ->and(validateCode()['valid'])->toBeTrue();
});

it('keeps a first-order-only code to customers without a paid order', function () {
    $this->fakePromotionCode('TEN', promotion: ['restrictions' => ['first_time_transaction' => true]]);
    $returning = User::factory()->create();
    paidOrderWith(null, ['user_id' => $returning->id]);
    paidOrderWith(null, ['user_id' => null, 'guest_email' => 'Guest@Example.test']);

    expect(validateCode(user: $returning))->toMatchArray(['valid' => false, 'error' => __('coupon.first_order_only')])
        ->and(validateCode(email: 'guest@example.test')['valid'])->toBeFalse()
        ->and(validateCode(user: User::factory()->create())['valid'])->toBeTrue()
        ->and(validateCode(email: 'new@example.test')['valid'])->toBeTrue()
        ->and(validateCode()['valid'])->toBeTrue();
});

it('fails closed with a retry message when Stripe cannot be reached', function () {
    $this->fakePromotionCode('TEN');
    $this->stripeHttp->responses['GET /v1/promotion_codes'] = ['__status' => 500, 'error' => ['message' => 'down']];

    expect(validateCode())->toMatchArray(['valid' => false, 'error' => __('coupon.check_failed')]);
});

it('limits coupon tries to ten a minute', function () {
    $this->fakePromotionCode('TEN', found: false);

    foreach (range(1, 10) as $try) {
        $this->postJson('/cart/apply-coupon', ['code' => 'GUESS'.$try])->assertStatus(422);
    }

    $this->postJson('/cart/apply-coupon', ['code' => 'GUESS11'])->assertStatus(429);
});
