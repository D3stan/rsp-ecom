<?php

use App\Models\User;
use Illuminate\Foundation\Testing\WithoutMiddleware;

uses(WithoutMiddleware::class);

test('confirm password screen can be rendered', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/confirm-password');

    $response->assertStatus(200);
});

test('password can be confirmed', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/confirm-password', [
        'password' => 'password',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();
});

test('password is not confirmed with invalid password', function () {
    $user = User::factory()->create();

    // Test with withoutExceptionHandling to see the actual exception
    $this->withoutExceptionHandling();
    
    $this->expectException(\Illuminate\Validation\ValidationException::class);
    
    $this->actingAs($user)->post('/confirm-password', [
        'password' => 'wrong-password',
    ]);
});