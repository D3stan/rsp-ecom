<?php

use App\Models\Setting;
use App\Models\User;
use App\Services\EmailService;

// The admin page is covered by tests/Feature/Settings; this file pins what the email lifecycle reads from it.

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    config(['mail.contact_email' => 'contact@shop.test']);
});

it('saves the toggles and the admin address, which then become effective', function () {
    $this->actingAs($this->admin)
        ->patch(route('admin.settings.update', 'email'), ['order_refunded' => false, 'order_delivered' => false])
        ->assertSessionHasNoErrors();
    $this->actingAs($this->admin)
        ->patch(route('admin.settings.update', 'contact'), ['admin_notification_email' => 'owner@shop.test'])
        ->assertSessionHasNoErrors();

    expect(settings('email.order_refunded'))->toBeFalse()
        ->and(settings('email.order_delivered'))->toBeFalse()
        ->and(settings('email.order_cancelled'))->toBeTrue();
    expect(app(EmailService::class)->adminAddress())->toBe('owner@shop.test');
    expect(app(EmailService::class)->isEnabled('order_refunded'))->toBeFalse();
});

it('rejects an invalid admin notification email', function () {
    $this->actingAs($this->admin)
        ->patch(route('admin.settings.update', 'contact'), ['admin_notification_email' => 'nope'])
        ->assertSessionHasErrors('admin_notification_email');
});

it('falls back to the contact address when the admin address is cleared', function () {
    $this->actingAs($this->admin)
        ->patch(route('admin.settings.update', 'contact'), ['admin_notification_email' => null])
        ->assertSessionHasNoErrors();

    expect(app(EmailService::class)->adminAddress())->toBe('contact@shop.test');
});

it('exposes the refunded toggle to the settings page (enabled by default)', function () {
    $fields = null;
    $this->actingAs($this->admin)->get(route('admin.settings'))
        ->assertInertia(function ($page) use (&$fields) {
            $fields = collect($page->toArray()['props']['groups'])->firstWhere('key', 'email')['fields'];

            return $page;
        });

    expect(collect($fields)->firstWhere('key', 'email.order_refunded'))->toMatchArray(['value' => true, 'source' => 'default', 'type' => 'bool']);
});

it('reads the new catalogue keys directly, and the legacy aliases still reach the same value', function () {
    $emails = app(EmailService::class);

    expect($emails->isEnabled('order_shipped'))->toBeTrue();

    settings()->set('email.order_shipped', false);
    expect($emails->isEnabled('order_shipped'))->toBeFalse()
        ->and(Setting::get('order_shipped_enabled'))->toBeFalse();

    settings()->set('email.order_shipped', true);
    settings()->set('email.enabled', false);
    expect($emails->isEnabled('order_shipped'))->toBeFalse();

    settings()->set('contact.admin_notification_email', 'owner@shop.test');
    expect($emails->adminAddress())->toBe('owner@shop.test');
});
