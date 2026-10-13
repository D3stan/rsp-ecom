<?php

namespace Tests\Feature\Orders;

use App\Models\Order;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderMigrationsTest extends TestCase
{
    use RefreshDatabase;

    private function migration(string $file): object
    {
        return require database_path("migrations/{$file}.php");
    }

    public function test_orders_have_a_locale_column_defaulting_to_italian(): void
    {
        $this->assertTrue(Schema::hasColumn('orders', 'locale'));

        $order = Order::factory()->create();

        $this->assertSame('it', $order->fresh()->locale);
    }

    public function test_two_orders_cannot_share_a_checkout_session(): void
    {
        Order::factory()->create(['stripe_checkout_session_id' => 'cs_same']);

        $this->expectException(UniqueConstraintViolationException::class);

        Order::factory()->create(['stripe_checkout_session_id' => 'cs_same']);
    }

    public function test_many_orders_without_a_checkout_session_are_allowed(): void
    {
        Order::factory()->count(3)->create(['stripe_checkout_session_id' => null]);

        $this->assertSame(3, Order::count());
    }

    public function test_unique_index_migration_refuses_to_run_over_existing_duplicates_and_deletes_nothing(): void
    {
        $migration = $this->migration('2026_10_13_100000_add_unique_index_to_orders_stripe_checkout_session_id');
        $migration->down();

        Order::factory()->create(['stripe_checkout_session_id' => 'cs_dup']);
        Order::factory()->create(['stripe_checkout_session_id' => 'cs_dup']);

        try {
            $migration->up();
            $this->fail('The migration must throw when duplicates exist');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('cs_dup', $e->getMessage());
            $this->assertStringContainsString('No data was changed', $e->getMessage());
        }

        $this->assertSame(2, Order::where('stripe_checkout_session_id', 'cs_dup')->count());
    }

    public function test_unique_index_migration_is_reversible(): void
    {
        $migration = $this->migration('2026_10_13_100000_add_unique_index_to_orders_stripe_checkout_session_id');

        $migration->down();
        Order::factory()->create(['stripe_checkout_session_id' => 'cs_again']);
        Order::factory()->create(['stripe_checkout_session_id' => 'cs_again']);
        Order::where('stripe_checkout_session_id', 'cs_again')->orderByDesc('id')->first()->delete();

        $migration->up();

        $this->expectException(UniqueConstraintViolationException::class);
        Order::factory()->create(['stripe_checkout_session_id' => 'cs_again']);
    }

    public function test_locale_migration_is_reversible(): void
    {
        $migration = $this->migration('2026_10_13_100100_add_locale_to_orders_table');

        $migration->down();
        $this->assertFalse(Schema::hasColumn('orders', 'locale'));

        $migration->up();
        $this->assertTrue(Schema::hasColumn('orders', 'locale'));
    }
}
