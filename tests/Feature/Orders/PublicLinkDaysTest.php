<?php

use App\Mail\OrderConfirmation;
use App\Models\Order;
use App\Models\User;
use App\Settings\Settings;
use Tests\Feature\Mail\Content\Support\EmailFixtures as Fx;

function publicLinkExpiresInDays(Order $order): int
{
    parse_str((string) parse_url($order->publicUrl(), PHP_URL_QUERY), $query);

    return (int) round((((int) $query['expires']) - now()->timestamp) / 86400);
}

it('signs the guest order link for 30 days by default', function () {
    $order = Order::factory()->create(['user_id' => null, 'guest_email' => 'guest@example.test']);

    expect(Order::publicUrlDays())->toBe(30)
        ->and(publicLinkExpiresInDays($order))->toBe(30);
});

it('follows orders.public_link_days without a restart', function () {
    $order = Order::factory()->create(['user_id' => null, 'guest_email' => 'guest@example.test']);

    app(Settings::class)->set('orders.public_link_days', 7, User::factory()->create(['role' => 'admin']));

    expect(Order::publicUrlDays())->toBe(7)
        ->and(publicLinkExpiresInDays($order))->toBe(7);
});

it('prints the configured number of days in the guest emails', function () {
    app(Settings::class)->set('orders.public_link_days', 12, User::factory()->create(['role' => 'admin']));
    $order = Fx::order();

    expect(Fx::deliver(new OrderConfirmation($order), 'en')->getTextBody())->toContain('valid for 12 days');
});

it('shows the configured number of days on the expired-link page', function () {
    app(Settings::class)->set('orders.public_link_days', 9, User::factory()->create(['role' => 'admin']));
    $order = Order::factory()->create(['user_id' => null, 'guest_email' => 'guest@example.test']);
    $url = $order->publicUrl();

    $this->get(substr($url, 0, -1).(str_ends_with($url, 'a') ? 'b' : 'a'))
        ->assertStatus(403)
        ->assertInertia(fn ($page) => $page
            ->component('Orders/GuestInvalid')
            ->where('settings', fn ($shared) => collect($shared)->get('orders.public_link_days') === 9));
});

it('keeps the {days} placeholder in the expired-link copy of every locale', function () {
    foreach (['en', 'it', 'de', 'es', 'fr'] as $locale) {
        $strings = json_decode(file_get_contents(lang_path("{$locale}.json")), true, flags: JSON_THROW_ON_ERROR);

        expect($strings['guest_order']['invalid_body'])->toContain('{days}')->not->toContain('30');
    }
});
