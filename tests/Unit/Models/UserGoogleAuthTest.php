<?php

namespace Tests\Unit\Models;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserGoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_have_google_id(): void
    {
        $user = User::factory()->create([
            'google_id' => '123456789',
        ]);

        $this->assertEquals('123456789', $user->google_id);
    }

    public function test_user_can_have_avatar(): void
    {
        $user = User::factory()->create([
            'avatar' => 'https://example.com/avatar.jpg',
        ]);

        $this->assertEquals('https://example.com/avatar.jpg', $user->avatar);
    }

    public function test_user_password_can_be_null(): void
    {
        $user = User::factory()->create([
            'password' => null,
        ]);

        $this->assertNull($user->password);
    }

    public function test_google_id_is_unique(): void
    {
        User::factory()->create([
            'google_id' => '123456789',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        
        User::factory()->create([
            'google_id' => '123456789',
        ]);
    }

    public function test_user_can_be_created_with_google_fields(): void
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'google_id' => '123456789',
            'avatar' => 'https://example.com/avatar.jpg',
            'email_verified_at' => now(),
            'role' => 'customer',
            'is_active' => true,
            'password' => null,
        ]);

        $this->assertNotNull($user);
        $this->assertEquals('123456789', $user->google_id);
        $this->assertEquals('https://example.com/avatar.jpg', $user->avatar);
        $this->assertNull($user->password);
    }
}
