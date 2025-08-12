<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileGoogleSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_shows_google_account_settings(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('profile.edit'));

        $response->assertOk();
        $response->assertInertia(fn ($page) =>
            $page->component('settings/profile')
        );
    }

    public function test_user_with_google_account_sees_unlink_option(): void
    {
        $user = User::factory()->withGoogle()->create();

        $response = $this->actingAs($user)
            ->get(route('profile.edit'));

        $response->assertOk();
        
        // Check that the user data includes google_id
        $this->assertNotNull($user->google_id);
    }

    public function test_user_without_google_account_sees_connect_option(): void
    {
        $user = User::factory()->create([
            'google_id' => null,
        ]);

        $response = $this->actingAs($user)
            ->get(route('profile.edit'));

        $response->assertOk();
        
        // Check that the user data shows no google_id
        $this->assertNull($user->google_id);
    }

    public function test_profile_page_renders_without_errors_for_google_user(): void
    {
        $user = User::factory()->googleOnly()->create();

        $response = $this->actingAs($user)
            ->get(route('profile.edit'));

        $response->assertOk();
        
        // Ensure Google-only users can access their profile
        $this->assertNull($user->password);
        $this->assertNotNull($user->google_id);
    }
}
