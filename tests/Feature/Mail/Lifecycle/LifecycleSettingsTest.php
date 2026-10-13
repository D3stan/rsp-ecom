<?php

use App\Models\Setting;
use App\Models\User;
use App\Services\EmailService;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    config(['mail.contact_email' => 'contact@shop.test']);
});

function emailSettingsPayload(array $overrides = []): array
{
    return array_merge([
        'email_notifications_enabled' => true,
        'order_confirmation_enabled' => true,
        'order_shipped_enabled' => true,
        'order_delivered_enabled' => true,
        'order_cancelled_enabled' => true,
        'order_refunded_enabled' => true,
        'newsletter_enabled' => false,
        'from_email' => 'noreply@shop.test',
        'from_name' => 'Shop',
        'admin_notification_email' => 'owner@shop.test',
    ], $overrides);
}

it('saves the new toggles and the admin address, which then becomes effective', function () {
    $this->actingAs($this->admin)
        ->patch(route('admin.settings.update-email'), emailSettingsPayload(['order_refunded_enabled' => false, 'order_delivered_enabled' => false]))
        ->assertSessionHasNoErrors();

    expect(Setting::get('order_refunded_enabled'))->toBeFalse()
        ->and(Setting::get('order_delivered_enabled'))->toBeFalse()
        ->and(Setting::get('order_cancelled_enabled'))->toBeTrue();
    expect(app(EmailService::class)->adminAddress())->toBe('owner@shop.test');
    expect(app(EmailService::class)->isEnabled('order_refunded'))->toBeFalse();
});

it('rejects an invalid admin notification email', function () {
    $this->actingAs($this->admin)
        ->patch(route('admin.settings.update-email'), emailSettingsPayload(['admin_notification_email' => 'nope']))
        ->assertSessionHasErrors('admin_notification_email');
});

it('falls back to the contact address when the admin address is cleared', function () {
    $this->actingAs($this->admin)
        ->patch(route('admin.settings.update-email'), emailSettingsPayload(['admin_notification_email' => null]))
        ->assertSessionHasNoErrors();

    expect(app(EmailService::class)->adminAddress())->toBe('contact@shop.test');
});

it('exposes the refunded toggle to the settings page (enabled by default)', function () {
    $this->actingAs($this->admin)->get(route('admin.settings'))
        ->assertInertia(fn ($page) => $page->where('settings.order_refunded_enabled', true));
});
