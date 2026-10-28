<?php

use App\Mail\WelcomeEmail;
use App\Models\User;
use Tests\Feature\Mail\Content\Support\EmailFixtures as Fx;

// store.name is DISPLAY only: app.name keeps naming caches and cookies (.ai/rules/settings.md).

it('shows the store name setting in the transactional mail layout and subjects', function () {
    settings()->set('store.name', 'Moto Luci Srl');
    $before = config('app.name');

    $mail = Fx::deliver(new WelcomeEmail(User::factory()->create(['name' => 'Anna Verdi'])), 'en');

    expect($mail->getSubject())->toContain('Moto Luci Srl')
        ->and($mail->getHtmlBody())->toContain('Moto Luci Srl')
        ->and($mail->getTextBody())->toContain('Moto Luci Srl')
        ->and(config('app.name'))->toBe($before);
});

it('shows the store name setting in page titles and the shared name prop', function () {
    settings()->set('store.name', 'Moto Luci Srl');

    $this->get(route('contact'))
        ->assertOk()
        ->assertSee('Contact Us – Moto Luci Srl', false)
        ->assertInertia(fn ($page) => $page->where('name', 'Moto Luci Srl'));
});

it('shares the public contact and social settings with every page', function () {
    settings()->set('contact.phone', '+39 02 1234567');
    settings()->set('social.instagram', 'https://instagram.com/motoluci');

    $this->get(route('contact'))->assertInertia(fn ($page) => $page->where('settings', fn ($shared) => collect($shared)->get('contact.phone') === '+39 02 1234567'
        && collect($shared)->get('social.instagram') === 'https://instagram.com/motoluci'
        && collect($shared)->get('social.facebook') === ''));
});
