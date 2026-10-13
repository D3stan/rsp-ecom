<?php

namespace Tests\Feature\Security;

use App\Models\Address;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GuestOrderPageTest extends TestCase
{
    use RefreshDatabase;

    private function guestOrder(array $attributes = []): Order
    {
        $order = Order::factory()->create(array_merge([
            'user_id' => null,
            'guest_email' => 'secret-buyer@example.test',
            'guest_phone' => '+39 333 000 1111',
            'guest_session_id' => 'guest-session-secret',
            'status' => 'shipped',
            'payment_status' => 'succeeded',
            'tracking_number' => 'TRK123456',
            'shipping_address_id' => Address::factory()->shipping()->create([
                'user_id' => null,
                'first_name' => 'Mario',
                'last_name' => 'Rossi',
                'address_line_1' => 'Via Roma 10',
                'city' => 'Milano',
                'postal_code' => '20100',
                'country' => 'IT',
            ])->id,
        ], $attributes));

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => null,
            'product_name' => 'Anello Argento',
            'quantity' => 2,
            'price' => 25.00,
            'total' => 50.00,
        ]);

        return $order;
    }

    public function test_order_number_alone_does_not_open_the_page(): void
    {
        $order = $this->guestOrder();

        $response = $this->get('/orders/'.$order->order_number);

        $response->assertStatus(403);
        $response->assertInertia(fn (Assert $page) => $page->component('Orders/GuestInvalid'));
        $this->assertStringNotContainsString('Via Roma', $response->getContent());
        $this->assertStringNotContainsString('TRK123456', $response->getContent());
    }

    public function test_valid_signed_link_shows_the_order(): void
    {
        $order = $this->guestOrder();

        $response = $this->get($order->publicUrl());

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Orders/GuestShow')
            ->where('order.order_number', $order->order_number)
            ->where('order.status', 'shipped')
            ->where('order.tracking_number', 'TRK123456')
            ->where('order.items.0.name', 'Anello Argento')
            ->where('order.shipping_address.line1', 'Via Roma 10')
            ->where('order.shipping_address.city', 'Milano')
            ->has('supportEmail')
        );
    }

    public function test_page_never_exposes_email_phone_or_session_id(): void
    {
        $order = $this->guestOrder();

        $content = $this->get($order->publicUrl())->getContent();

        $this->assertStringNotContainsString('secret-buyer@example.test', $content);
        $this->assertStringNotContainsString('+39 333 000 1111', $content);
        $this->assertStringNotContainsString('guest-session-secret', $content);
    }

    public function test_page_is_not_cacheable_and_sends_no_referrer(): void
    {
        $order = $this->guestOrder();

        $response = $this->get($order->publicUrl());

        $response->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_tampered_signature_is_denied_with_the_friendly_page(): void
    {
        $order = $this->guestOrder();
        $url = $order->publicUrl();
        $tampered = substr($url, 0, -1).(str_ends_with($url, 'a') ? 'b' : 'a');

        $response = $this->get($tampered);

        $response->assertStatus(403);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Orders/GuestInvalid')
            ->has('supportEmail')
        );
    }

    public function test_signature_of_one_order_cannot_open_another(): void
    {
        $mine = $this->guestOrder();
        $other = $this->guestOrder(['tracking_number' => 'OTHERTRACK']);

        $url = str_replace($mine->order_number, $other->order_number, $mine->publicUrl());

        $response = $this->get($url);

        $response->assertStatus(403);
        $response->assertInertia(fn (Assert $page) => $page->component('Orders/GuestInvalid'));
        $this->assertStringNotContainsString('OTHERTRACK', $response->getContent());
    }

    public function test_extra_query_parameters_invalidate_the_signature(): void
    {
        $order = $this->guestOrder();

        $this->get($order->publicUrl().'&extra=1')->assertStatus(403);
    }

    public function test_expired_link_is_denied_with_the_friendly_page(): void
    {
        $order = $this->guestOrder();
        $url = URL::temporarySignedRoute('orders.guest.show', now()->addMinutes(5), ['order' => $order->order_number]);

        $this->get($url)->assertOk();

        $this->travel(6)->minutes();

        $response = $this->get($url);
        $response->assertStatus(403);
        $response->assertInertia(fn (Assert $page) => $page->component('Orders/GuestInvalid'));
    }

    public function test_unknown_order_number_looks_exactly_like_a_bad_signature(): void
    {
        $url = URL::temporarySignedRoute('orders.guest.show', now()->addDay(), ['order' => 'ORD-2026-999999']);

        $response = $this->get($url);

        $response->assertStatus(403);
        $response->assertInertia(fn (Assert $page) => $page->component('Orders/GuestInvalid'));
    }

    public function test_public_url_is_signed_and_expires_in_about_thirty_days(): void
    {
        $order = $this->guestOrder();

        parse_str(parse_url($order->publicUrl(), PHP_URL_QUERY), $query);

        $this->assertArrayHasKey('signature', $query);
        $this->assertEqualsWithDelta(now()->addDays(30)->timestamp, (int) $query['expires'], 5);
        $this->assertStringContainsString('/orders/'.$order->order_number, $order->publicUrl());
    }

    public function test_guest_page_works_without_any_session_or_cookie(): void
    {
        $order = $this->guestOrder();

        // a fresh client with no cookies at all (mail client in-app browser): the signature is enough
        $this->flushSession();
        $this->get($order->publicUrl())->assertOk();
    }
}
