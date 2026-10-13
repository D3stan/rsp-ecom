<?php

namespace Tests\Feature\Orders;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class DuplicateSessionsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_success_when_there_are_no_duplicates(): void
    {
        Order::factory()->create(['stripe_checkout_session_id' => 'cs_unique']);
        Order::factory()->count(2)->create(['stripe_checkout_session_id' => null]);

        $this->artisan('orders:duplicate-sessions')
            ->expectsOutputToContain('No duplicate')
            ->assertExitCode(0);
    }

    public function test_lists_duplicate_groups_and_fails_without_changing_data(): void
    {
        $migration = require database_path('migrations/2026_10_13_100000_add_unique_index_to_orders_stripe_checkout_session_id.php');
        $migration->down(); // simulate a database that predates the unique index

        $a = Order::factory()->create(['stripe_checkout_session_id' => 'cs_dup', 'order_number' => 'ORD-X-1']);
        $b = Order::factory()->create(['stripe_checkout_session_id' => 'cs_dup', 'order_number' => 'ORD-X-2']);
        Order::factory()->create(['stripe_checkout_session_id' => 'cs_fine']);

        $exitCode = Artisan::call('orders:duplicate-sessions');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('cs_dup', $output);
        $this->assertStringContainsString('ORD-X-1', $output);
        $this->assertStringContainsString('ORD-X-2', $output);
        $this->assertStringNotContainsString('cs_fine', $output);

        $this->assertSame(3, Order::count());
        $this->assertNotNull(Order::find($a->id));
        $this->assertNotNull(Order::find($b->id));
    }
}
