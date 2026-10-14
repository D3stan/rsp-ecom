<?php

use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeding production must never create a login anyone can guess (admin@example.com / password), nor demo
 * customers. The admin is created only from ADMIN_EMAIL / ADMIN_PASSWORD (config app.admin_seed).
 */
function seedUsersIn(string $environment): void
{
    app()['env'] = $environment;

    // The real production path: `php artisan db:seed --force` (without --force artisan refuses in production)
    test()->artisan('db:seed', ['--class' => UserSeeder::class, '--force' => true])->assertExitCode(0);
}

test('production seeding creates no known admin login and no demo users', function () {
    config(['app.admin_seed.email' => null, 'app.admin_seed.password' => null]);

    seedUsersIn('production');

    $admin = User::where('email', 'admin@example.com')->first();
    expect($admin === null || ! Hash::check('password', $admin->password))->toBeTrue('production has admin@example.com / password')
        ->and(User::where('email', 'test@example.com')->exists())->toBeFalse()
        ->and(User::count())->toBe(0);
});

test('production seeding creates the admin from ADMIN_EMAIL / ADMIN_PASSWORD', function () {
    config(['app.admin_seed.email' => 'owner@shop.test', 'app.admin_seed.password' => 'a-long-secret-passphrase']);

    seedUsersIn('production');

    $admin = User::where('email', 'owner@shop.test')->first();
    expect($admin)->not->toBeNull()
        ->and($admin->role)->toBe('admin')
        ->and($admin->email_verified_at)->not->toBeNull()
        ->and(Hash::check('a-long-secret-passphrase', $admin->password))->toBeTrue()
        ->and(User::where('email', 'admin@example.com')->exists())->toBeFalse()
        ->and(User::count())->toBe(1);
});

test('non-production seeding is unchanged: demo admin, test user and fake customers', function () {
    seedUsersIn('local');

    $admin = User::where('email', 'admin@example.com')->first();
    expect($admin->role)->toBe('admin')
        ->and(Hash::check('password', $admin->password))->toBeTrue()
        ->and(User::where('email', 'test@example.com')->exists())->toBeTrue()
        ->and(User::count())->toBeGreaterThan(2);
});
