<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->customer = User::factory()->create(['role' => 'customer', 'name' => 'Zorblax Rossi', 'is_active' => true]);
});

it('lets an admin list and view customers', function () {
    Order::factory()->create(['user_id' => $this->customer->id, 'payment_status' => 'succeeded', 'total_amount' => 40]);
    Order::factory()->create(['user_id' => $this->customer->id, 'payment_status' => 'pending', 'total_amount' => 99]);

    $this->actingAs($this->admin)->get(route('admin.customers.index', ['search' => 'Zorblax']))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('Admin/Customers/Index')
            ->has('customers.data', 1)
            ->where('customers.data.0.orders_count', 2)
            ->where('customers.data.0.total_spent', 40));

    $this->actingAs($this->admin)->get(route('admin.customers.show', $this->customer))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('Admin/Customers/Show')->has('orders', 2));
});

it('keeps guests and non-admins out, and never lists admins', function () {
    $this->get(route('admin.customers.index'))->assertRedirect();
    $this->actingAs($this->customer)->get(route('admin.customers.index'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('admin.customers.show', $this->admin))->assertNotFound();
});

it('edits a customer and validates a unique email', function () {
    $other = User::factory()->create();

    $this->actingAs($this->admin)->patch(route('admin.customers.update', $this->customer), [
        'name' => 'Mario B', 'email' => $other->email, 'email_verified' => true,
    ])->assertSessionHasErrors('email');

    $this->actingAs($this->admin)->patch(route('admin.customers.update', $this->customer), [
        'name' => 'Mario B', 'email' => 'new@example.com', 'email_verified' => false,
    ])->assertSessionHas('success');

    expect($this->customer->fresh())->name->toBe('Mario B')->email->toBe('new@example.com')->email_verified_at->toBeNull();
});

it('deactivates and reactivates; an inactive customer cannot sign in or keep a session', function () {
    $this->customer->update(['password' => bcrypt('secret-pass-1')]);

    $this->actingAs($this->admin)->patch(route('admin.customers.toggle-active', $this->customer))->assertSessionHas('success');
    expect($this->customer->fresh()->is_active)->toBeFalse();

    auth()->logout();
    $this->post('/login', ['email' => $this->customer->email, 'password' => 'secret-pass-1'])->assertSessionHasErrors('email');
    $this->assertGuest();

    // existing session is ended on the next request
    $this->actingAs($this->customer->fresh())->get('/dashboard')->assertRedirect(route('login'));
    $this->assertGuest();

    $this->actingAs($this->admin)->patch(route('admin.customers.toggle-active', $this->customer));
    expect($this->customer->fresh()->is_active)->toBeTrue();
});

it('does not let an admin deactivate an admin', function () {
    $this->actingAs($this->admin)->patch(route('admin.customers.toggle-active', $this->admin))->assertNotFound();
});

it('sends a password reset link', function () {
    Notification::fake();

    $this->actingAs($this->admin)->post(route('admin.customers.reset-link', $this->customer))->assertSessionHas('success');
});
