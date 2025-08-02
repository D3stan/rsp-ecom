<?php

namespace Tests\Unit\Models;

use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Billable;

class UserCashierTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_billable_trait()
    {
        $user = new User();
        
        $this->assertContains(Billable::class, class_uses_recursive($user));
    }

    public function test_user_can_create_stripe_customer()
    {
        // This test requires actual Stripe API keys to work
        // For now, we just test that the method exists
        $user = User::factory()->create();
        
        $this->assertTrue(method_exists($user, 'createAsStripeCustomer'));
        $this->assertTrue(method_exists($user, 'hasDefaultPaymentMethod'));
        $this->assertTrue(method_exists($user, 'charge'));
        $this->assertTrue(method_exists($user, 'checkout'));
    }

    public function test_user_stripe_fields_are_accessible()
    {
        $user = User::factory()->create();
        
        // Test that we can set Stripe-related fields
        $user->stripe_id = 'cus_test123';
        $user->pm_type = 'card';
        $user->pm_last_four = '4242';
        $user->save();
        
        $this->assertEquals('cus_test123', $user->stripe_id);
        $this->assertEquals('card', $user->pm_type);
        $this->assertEquals('4242', $user->pm_last_four);
    }
}
