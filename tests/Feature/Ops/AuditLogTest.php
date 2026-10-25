<?php

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\User;
use App\Settings\SystemInfo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\Support\CatalogFixtures;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin', 'name' => 'Marta Admin']);
});

describe('admin changes', function () {
    it('records what an admin changed on a product, with the old and new value', function () {
        $product = CatalogFixtures::product(['name' => 'Angel Eye']);

        $this->actingAs($this->admin);
        $product->update(['name' => 'Angel Eye Pro']);

        $entry = AuditLog::where('action', 'product.updated')->sole();
        expect($entry->actor_id)->toBe($this->admin->id)
            ->and($entry->subject_type)->toBe('Product')
            ->and($entry->subject_id)->toBe($product->id)
            ->and($entry->subject_label)->toBe('Angel Eye Pro')
            ->and($entry->changes['name'])->toBe(['Angel Eye', 'Angel Eye Pro']);
    });

    it('records the creation and the deletion of a product', function () {
        $this->actingAs($this->admin);

        $product = CatalogFixtures::product(['name' => 'Fresh Product']);
        $product->delete();

        expect(AuditLog::where('action', 'product.created')->where('subject_label', 'Fresh Product')->exists())->toBeTrue()
            ->and(AuditLog::where('action', 'product.deleted')->where('subject_label', 'Fresh Product')->exists())->toBeTrue();
    });

    it('records an admin deactivating or promoting a customer', function () {
        $customer = User::factory()->create(['role' => 'customer', 'is_active' => true]);

        $this->actingAs($this->admin);
        $customer->update(['is_active' => false]);

        expect(AuditLog::where('action', 'user.updated')->sole()->changes['is_active'])->toBe([true, false]);
    });

    it('ignores changes made by a customer, by nobody, or by an update that only touched secrets', function () {
        $product = CatalogFixtures::product(['name' => 'Angel Eye']);
        $customer = User::factory()->create(['role' => 'customer']);

        $product->update(['name' => 'Changed by nobody']);
        $this->actingAs($customer);
        $product->update(['name' => 'Changed by a customer']);

        $this->actingAs($this->admin);
        $customer->update(['password' => Hash::make('a-new-password'), 'remember_token' => 'abc']);

        expect(AuditLog::where('action', 'product.updated')->exists())->toBeFalse()
            ->and(AuditLog::where('action', 'user.updated')->exists())->toBeFalse();
    });

    it('never stores a secret next to a change that is worth recording', function () {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($this->admin);
        $customer->update(['role' => 'admin', 'password' => Hash::make('a-new-password')]);

        $changes = AuditLog::where('action', 'user.updated')->sole()->changes;
        expect($changes)->toHaveKey('role')->not->toHaveKey('password');
    });

    it('keeps the entry readable after its subject is gone', function () {
        $this->actingAs($this->admin);
        $product = CatalogFixtures::product(['name' => 'Doomed']);
        $product->delete();

        expect(Product::find($product->id))->toBeNull()
            ->and(AuditLog::where('action', 'product.deleted')->sole()->subject_label)->toBe('Doomed');
    });
});

describe('admin sign-ins', function () {
    beforeEach(function () {
        Http::preventStrayRequests();
        config(['services.telegram.token' => 'secret-token', 'services.telegram.chat_id' => '4242']);
    });

    function signInFrom(User $user, string $ip): void
    {
        request()->server->set('REMOTE_ADDR', $ip);
        Auth::login($user);
        Auth::logout();
    }

    it('records every admin sign-in and tells Telegram about a new address only', function () {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        signInFrom($this->admin, '203.0.113.9');
        signInFrom($this->admin, '203.0.113.9');
        signInFrom($this->admin, '198.51.100.4');

        expect(AuditLog::where('action', 'admin.login')->count())->toBe(3);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => str_contains($request['text'], 'Marta Admin'));
    });

    it('does not record a customer sign-in', function () {
        Http::fake();

        signInFrom(User::factory()->create(['role' => 'customer']), '203.0.113.9');

        expect(AuditLog::count())->toBe(0);
        Http::assertNothingSent();
    });
});

it('lists the latest admin activity in the system panel', function () {
    AuditLog::factory()->create(['actor_id' => $this->admin->id, 'action' => 'product.updated', 'subject_label' => 'Angel Eye', 'created_at' => now()->subHour()]);
    AuditLog::factory()->create(['actor_id' => $this->admin->id, 'action' => 'admin.login', 'subject_label' => 'Marta Admin']);

    $rows = collect(app(SystemInfo::class)->panel()['sections'])
        ->flatMap(fn ($section) => $section['items'])
        ->firstWhere('id', 'recent_admin_activity')['value'];

    expect($rows)->toHaveCount(2)
        ->and($rows[0])->toMatchArray(['admin' => 'Marta Admin', 'action' => 'admin.login'])
        ->and($rows[1]['subject'])->toBe('Angel Eye');
});
