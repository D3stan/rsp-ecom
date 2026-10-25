<?php

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

it('adds a request id header to web responses', function () {
    $response = $this->get(route('home'));

    expect(Str::isUlid($response->headers->get('X-Request-Id')))->toBeTrue();
});

it('gives every request its own request id', function () {
    $first = $this->get(route('home'))->headers->get('X-Request-Id');
    $second = $this->get(route('home'))->headers->get('X-Request-Id');

    expect($first)->not->toBe($second);
});

it('shares the request id and a guest area with the log context', function () {
    $requestId = $this->get(route('home'))->headers->get('X-Request-Id');

    expect(Log::sharedContext())->toMatchArray(['request_id' => $requestId, 'user_id' => null, 'area' => 'guest']);
});

it('tags a signed in customer and an admin differently in the log context', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $this->actingAs($customer)->get(route('home'));
    expect(Log::sharedContext())->toMatchArray(['user_id' => $customer->id, 'area' => 'customer']);

    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->get(route('home'));
    expect(Log::sharedContext())->toMatchArray(['user_id' => $admin->id, 'area' => 'admin']);
});
