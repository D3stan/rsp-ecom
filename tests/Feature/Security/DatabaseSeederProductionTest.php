<?php

use App\Models\Category;
use App\Models\Order;
use App\Models\Page;
use App\Models\Product;
use App\Models\Review;
use App\Models\Setting;
use App\Models\Size;
use App\Models\User;
use App\Models\Wishlist;

/**
 * `php artisan db:seed --force` on production seeds only what the shop needs to run (settings defaults and the
 * admin from ADMIN_EMAIL / ADMIN_PASSWORD): no demo catalogue, orders, reviews or wishlists, and it never prints
 * the demo credentials.
 */
test('production db:seed creates no demo data and prints no demo credentials', function () {
    config(['app.admin_seed.email' => null, 'app.admin_seed.password' => null]);
    app()['env'] = 'production';

    $this->artisan('db:seed', ['--force' => true])
        ->doesntExpectOutputToContain('admin@example.com')
        ->doesntExpectOutputToContain('test@example.com')
        ->doesntExpectOutputToContain('/ password')
        ->expectsOutputToContain('Production: demo data skipped')
        ->assertExitCode(0);

    expect(Setting::count())->toBeGreaterThan(0)
        ->and(User::count())->toBe(0)
        ->and(Category::count())->toBe(0)
        ->and(Size::count())->toBe(0)
        ->and(Page::count())->toBe(0)
        ->and(Product::count())->toBe(0)
        ->and(Order::count())->toBe(0)
        ->and(Review::count())->toBe(0)
        ->and(Wishlist::count())->toBe(0);
});

test('production db:seed creates only the configured admin', function () {
    config(['app.admin_seed.email' => 'owner@shop.test', 'app.admin_seed.password' => 'a-long-secret-passphrase']);
    app()['env'] = 'production';

    $this->artisan('db:seed', ['--force' => true])->assertExitCode(0);

    expect(User::pluck('email')->all())->toBe(['owner@shop.test'])
        ->and(Product::count())->toBe(0)
        ->and(Order::count())->toBe(0);
});
