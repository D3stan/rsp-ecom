<?php

use App\Models\Setting;
use App\Models\SettingChange;
use App\Models\User;
use App\Services\EmailService;
use App\Settings\Settings;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
});

it('keeps the route names the UI relies on', function () {
    expect(route('admin.settings', [], false))->toBe('/admin/settings')
        ->and(route('admin.settings.update', ['group' => 'tax'], false))->toBe('/admin/settings/tax')
        ->and(route('admin.settings.reset', ['key' => 'tax.rate'], false))->toBe('/admin/settings/tax.rate')
        ->and(route('admin.settings.history', ['group' => 'tax'], false))->toBe('/admin/settings/tax/history');
});

it('redirects guests and forbids non-admins on every settings route', function () {
    $customer = User::factory()->create(['role' => 'customer']);

    $this->get(route('admin.settings'))->assertRedirect();
    $this->patch(route('admin.settings.update', 'tax'), ['rate' => 5])->assertRedirect();

    foreach ([
        fn () => $this->actingAs($customer)->get(route('admin.settings')),
        fn () => $this->actingAs($customer)->patch(route('admin.settings.update', 'tax'), ['rate' => 5]),
        fn () => $this->actingAs($customer)->delete(route('admin.settings.reset', 'tax.rate')),
        fn () => $this->actingAs($customer)->getJson(route('admin.settings.history', 'tax')),
    ] as $request) {
        $request()->assertForbidden();
    }

    expect(app(Settings::class)->get('tax.rate'))->toBe(22.0)
        ->and(Gate::forUser($customer)->allows('manage-settings'))->toBeFalse()
        ->and(Gate::forUser($this->admin)->allows('manage-settings'))->toBeTrue();
});

it('renders the index with the ordered groups, typed fields and the system panel', function () {
    $this->actingAs($this->admin)->get(route('admin.settings'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Settings/Index')
            ->has('groups', 15)
            ->where('groups.0.key', 'store')
            ->where('groups.0.label', 'Store')
            ->where('groups.0.icon', 'Store')
            ->has('groups.0.fields', 7)
            ->where('groups.0.fields.0.key', 'store.name')
            ->where('groups.0.fields.0.name', 'name')
            ->where('groups.0.fields.0.type', 'string')
            ->where('groups.0.fields.0.label', 'Store name')
            ->where('groups.0.fields.0.source', 'default')
            ->where('groups.0.fields.6.readonly', true)
            ->where('groups.13.key', 'newsletter')
            ->where('groups.14.key', 'system')
            ->where('groups.14.panel', true)
            ->has('system.sections', 4)
            ->where('system.sections.0.items.0.id', 'withdrawal_days')
            ->where('system.sections.0.items.0.value', 14)
            ->where('historyLimit', 20)
            ->has('groups.3.fields.0', fn ($field) => $field
                ->where('key', 'tax.rate')->where('type', 'float')->where('value', 22)->where('default', 22)
                ->where('unit', '%')->where('min', 0)->where('max', 100)->where('risk', 'high')->where('required', true)
                ->where('help', fn ($help) => is_string($help) && $help !== '')
                ->etc()));
});

it('never puts secrets, legacy rows or env keys in the props', function () {
    Setting::set('stripe_secret_key', 'sk_live_SECRETVALUE');
    Setting::set('stripe_public_key', 'pk_live_PUBLICVALUE');
    config(['cashier.secret' => 'sk_env_SECRETENV', 'cashier.key' => 'pk_env_PUBLICENV']);

    $html = $this->actingAs($this->admin)->get(route('admin.settings'))->assertOk()->getContent();

    foreach (['SECRETVALUE', 'PUBLICVALUE', 'SECRETENV', 'PUBLICENV', 'stripe_secret_key'] as $needle) {
        expect($html)->not->toContain($needle);
    }

    $this->actingAs($this->admin)->get(route('admin.settings'))
        ->assertInertia(fn ($page) => $page->where('system.sections.1.items.0.value', true));
});

it('saves a group, writes audit rows, flashes success and applies the change', function () {
    $this->actingAs($this->admin)
        ->from(route('admin.settings'))
        ->patch(route('admin.settings.update', 'tax'), ['rate' => '8.75', 'prices_include_tax' => false])
        ->assertRedirect(route('admin.settings'))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Settings saved.');

    $settings = app(Settings::class);
    expect($settings->get('tax.rate'))->toBe(8.75)
        ->and($settings->get('tax.prices_include_tax'))->toBeFalse()
        ->and(SettingChange::where('user_id', $this->admin->id)->pluck('key')->sort()->values()->all())->toBe(['tax.prices_include_tax', 'tax.rate']);
});

it('flashes a warning when nothing changed', function () {
    $this->actingAs($this->admin)->patch(route('admin.settings.update', 'tax'), ['rate' => 22])
        ->assertSessionHas('warning')
        ->assertSessionMissing('success');

    expect(SettingChange::count())->toBe(0);
});

it('validates every field with errors keyed by the short field name', function () {
    $this->actingAs($this->admin)->patch(route('admin.settings.update', 'tax'), ['rate' => 150])
        ->assertSessionHasErrors('rate');
    $this->actingAs($this->admin)->patch(route('admin.settings.update', 'catalog'), ['per_page' => 5])
        ->assertSessionHasErrors('per_page');
    $this->actingAs($this->admin)->patch(route('admin.settings.update', 'catalog'), ['per_page' => 61])
        ->assertSessionHasErrors('per_page');
    $this->actingAs($this->admin)->patch(route('admin.settings.update', 'contact'), ['support_email' => 'nope', 'admin_notification_email' => 'also nope'])
        ->assertSessionHasErrors(['support_email', 'admin_notification_email']);
    $this->actingAs($this->admin)->patch(route('admin.settings.update', 'social'), ['instagram' => 'javascript:alert(1)'])
        ->assertSessionHasErrors('instagram');
    $this->actingAs($this->admin)->patch(route('admin.settings.update', 'shipping'), ['destinations' => ['ITA']])
        ->assertSessionHasErrors('destinations.0');
    $this->actingAs($this->admin)->patch(route('admin.settings.update', 'shipping'), ['carriers' => [['code' => 'A B', 'name' => '', 'tracking_url' => 'x']]])
        ->assertSessionHasErrors(['carriers.0.code', 'carriers.0.name', 'carriers.0.tracking_url']);

    expect(app(Settings::class)->get('tax.rate'))->toBe(22.0)
        ->and(SettingChange::count())->toBe(0);
});

it('names list items and table cells in validation messages instead of the dotted path', function () {
    $response = $this->actingAs($this->admin)->patch(route('admin.settings.update', 'shipping'), [
        'destinations' => ['ITA'],
        'carriers' => [['code' => 'A B', 'name' => '', 'tracking_url' => 'x']],
    ]);

    $errors = session('errors')->getBag('default');

    expect($errors->first('destinations.0'))->toContain('Destination countries')->not->toContain('destinations.0')
        ->and($errors->first('carriers.0.code'))->toContain('Code')->not->toContain('carriers.0')
        ->and($errors->first('carriers.0.name'))->toContain('Name')->not->toContain('carriers.0')
        ->and($errors->first('carriers.0.tracking_url'))->toContain('Tracking link')->not->toContain('carriers.0');
});

it('rejects a minimum above its maximum and a default country outside the destinations', function () {
    $this->actingAs($this->admin)->patch(route('admin.settings.update', 'shipping'), [
        'dispatch_days_min' => 6, 'dispatch_days_max' => 2,
    ])->assertSessionHasErrors('dispatch_days_max');

    $this->actingAs($this->admin)->patch(route('admin.settings.update', 'shipping'), [
        'delivery_days_min' => 4, 'delivery_days_max' => 3,
    ])->assertSessionHasErrors('delivery_days_max');

    $this->actingAs($this->admin)->patch(route('admin.settings.update', 'shipping'), [
        'destinations' => ['DE', 'FR'], 'default_country' => 'IT',
    ])->assertSessionHasErrors('default_country');

    $this->actingAs($this->admin)->patch(route('admin.settings.update', 'shipping'), [
        'dispatch_days_min' => 2, 'dispatch_days_max' => 4, 'destinations' => ['DE', 'FR'], 'default_country' => 'FR',
    ])->assertSessionHasNoErrors();

    expect(app(Settings::class)->get('shipping.dispatch_days_max'))->toBe(4);
});

it('ignores read-only fields and unknown names, and 404s unknown groups', function () {
    $this->actingAs($this->admin)->patch(route('admin.settings.update', 'store'), ['currency' => 'USD', 'bogus' => 'x', 'name' => 'Moto Shop'])
        ->assertSessionHasNoErrors();

    expect(app(Settings::class)->get('store.currency'))->toBe('EUR')
        ->and(app(Settings::class)->get('store.name'))->toBe('Moto Shop');

    $this->actingAs($this->admin)->patch(route('admin.settings.update', 'nope'), ['a' => 1])->assertNotFound();
    $this->actingAs($this->admin)->patch(route('admin.settings.update', 'system'), ['a' => 1])->assertNotFound();
    $this->actingAs($this->admin)->delete(route('admin.settings.reset', 'store.currency'))->assertNotFound();
    $this->actingAs($this->admin)->delete(route('admin.settings.reset', 'nope.nope'))->assertNotFound();
});

it('resets one setting to its default and audits it', function () {
    app(Settings::class)->set('catalog.per_page', 30, $this->admin);

    $this->actingAs($this->admin)->delete(route('admin.settings.reset', 'catalog.per_page'))
        ->assertSessionHas('success');

    expect(app(Settings::class)->get('catalog.per_page'))->toBe(12)
        ->and(app(Settings::class)->source('catalog.per_page'))->toBe('default')
        ->and(SettingChange::where('key', 'catalog.per_page')->count())->toBe(2);
});

it('serves the last changes of a group as JSON, newest first, with the admin name', function () {
    $settings = app(Settings::class);
    $settings->set('tax.rate', 10, $this->admin);
    $settings->set('tax.rate', 12, $this->admin);
    $settings->set('catalog.per_page', 20, $this->admin);

    $response = $this->actingAs($this->admin)->getJson(route('admin.settings.history', 'tax'))->assertOk();

    $response->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.key', 'tax.rate')
        ->assertJsonPath('data.0.label', 'VAT rate')
        ->assertJsonPath('data.0.old', 10)
        ->assertJsonPath('data.0.new', 12)
        ->assertJsonPath('data.0.user.name', $this->admin->name)
        ->assertJsonPath('data.1.new', 10);

    $this->actingAs($this->admin)->getJson(route('admin.settings.history', 'nope'))->assertNotFound();
});

it('limits the history to the last 20 changes', function () {
    $settings = app(Settings::class);
    foreach (range(1, 25) as $i) {
        $settings->set('catalog.per_page', 5 + $i, $this->admin);
    }

    $this->actingAs($this->admin)->getJson(route('admin.settings.history', 'catalog'))
        ->assertJsonCount(20, 'data')
        ->assertJsonPath('data.0.new', 30);
});

it('makes email switches and the owner address effective for EmailService', function () {
    config(['mail.contact_email' => 'contact@shop.test']);

    $this->actingAs($this->admin)->patch(route('admin.settings.update', 'email'), ['order_refunded' => false, 'order_delivered' => false])
        ->assertSessionHasNoErrors();
    $this->actingAs($this->admin)->patch(route('admin.settings.update', 'contact'), ['admin_notification_email' => 'owner@shop.test'])
        ->assertSessionHasNoErrors();

    $emails = app(EmailService::class);
    expect($emails->isEnabled('order_refunded'))->toBeFalse()
        ->and($emails->isEnabled('order_delivered'))->toBeFalse()
        ->and($emails->isEnabled('order_cancelled'))->toBeTrue()
        ->and($emails->adminAddress())->toBe('owner@shop.test');

    $this->actingAs($this->admin)->patch(route('admin.settings.update', 'contact'), ['admin_notification_email' => null])
        ->assertSessionHasNoErrors();
    expect($emails->adminAddress())->toBe('contact@shop.test');
});

it('shares only the public settings as a typed Inertia prop', function () {
    app(Settings::class)->set('tax.rate', 8.5);

    $shared = null;
    $this->actingAs($this->admin)->get(route('admin.settings'))
        ->assertInertia(function ($page) use (&$shared) {
            $shared = $page->toArray()['props']['settings'];

            return $page;
        });

    expect($shared['tax.rate'])->toBe(8.5)
        ->and($shared['orders.public_link_days'])->toBe(30)
        ->and($shared['tax.prices_include_tax'])->toBeTrue()
        ->and($shared['shipping.destinations'])->toBe(['IT', 'DE', 'GB', 'FR', 'ES'])
        ->and($shared['store.currency'])->toBe('EUR')
        ->and($shared)->not->toHaveKeys(['store.company_iban', 'store.account_holder', 'contact.admin_notification_email', 'contact.form_recipient', 'email.enabled', 'reviews.auto_approve'])
        ->and(array_keys($shared))->toBe(array_keys(app(Settings::class)->public()));

    $guestShared = null;
    $this->get('/')->assertInertia(function ($page) use (&$guestShared) {
        $guestShared = $page->toArray()['props']['settings'];

        return $page;
    });
    expect($guestShared)->toHaveKey('store.name');
});

it('describes the system without secrets: scheduler, rate limits, retries, queue status', function () {
    $panel = app(App\Settings\SystemInfo::class)->panel();
    $items = collect($panel['sections'])->flatMap(fn ($s) => $s['items'])->keyBy('id');

    expect(collect($items['scheduled_tasks']['value'])->pluck('name')->all())->toContain('ops:check-queue')
        ->and(collect($items['rate_limits']['value'])->pluck('limit')->all())->toContain('5 / 1 min')
        ->and($items['job_retries']['value'][0])->toMatchArray(['name' => 'SendOrderEmail', 'tries' => 5, 'timeout' => 60])
        ->and($items['queue_health']['value'])->toBe(['status' => 'unknown', 'checked_at' => null])
        ->and($items['stripe_configured']['value'])->toBeBool();

    Artisan::call('ops:check-queue');
    // the sync queue in tests is "nothing to check": no status is recorded
    expect(app(App\Settings\SystemInfo::class)->queueHealth()['status'])->toBe('unknown');

    config(['queue.default' => 'database']);
    Artisan::call('ops:check-queue');
    expect(app(App\Settings\SystemInfo::class)->queueHealth()['status'])->toBe('ok');

    expect(json_encode($panel))->not->toMatch('/sk_|pk_|whsec_/');
});
