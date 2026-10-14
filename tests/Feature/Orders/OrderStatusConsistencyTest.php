<?php

namespace Tests\Feature\Orders;

use App\Http\Controllers\Admin\OrdersController;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class OrderStatusConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
    }

    private function order(string $status, string $paymentStatus): Order
    {
        return Order::factory()->create(['status' => $status, 'payment_status' => $paymentStatus]);
    }

    public function test_cancelling_an_unpaid_order_also_cancels_the_payment(): void
    {
        $order = $this->order('pending', 'pending');

        $this->assertTrue($order->cancel());

        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertSame('cancelled', $order->payment_status);
    }

    public function test_cancelling_a_processing_payment_cancels_the_payment_too(): void
    {
        $order = $this->order('processing', 'processing');

        $order->cancel();

        $this->assertSame('cancelled', $order->fresh()->payment_status);
    }

    public function test_cancelling_a_paid_order_keeps_the_captured_payment_status(): void
    {
        $order = $this->order('processing', 'succeeded');

        $this->assertTrue($order->cancel());

        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertSame('succeeded', $order->payment_status); // refund is a separate step
    }

    public function test_shipped_orders_cannot_be_cancelled_via_the_model_helper(): void
    {
        $order = $this->order('shipped', 'succeeded');

        $this->assertFalse($order->cancel());
        $this->assertSame('shipped', $order->fresh()->status);
    }

    public function test_an_explicit_payment_status_is_not_overridden_by_the_cancel_rule(): void
    {
        $order = $this->order('pending', 'pending');

        $order->update(['status' => 'cancelled', 'payment_status' => 'failed']);

        $this->assertSame('failed', $order->fresh()->payment_status);
    }

    public function test_other_status_changes_leave_payment_status_alone(): void
    {
        $order = $this->order('pending', 'pending');

        $order->update(['status' => 'processing']);

        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_admin_status_update_to_cancelled_closes_an_unpaid_payment(): void
    {
        $order = $this->order('processing', 'pending');

        $this->actingAs($this->admin())
            ->patch(route('admin.orders.update-status', $order), ['status' => 'cancelled'])
            ->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertSame('cancelled', $order->payment_status);
    }

    public function test_admin_cancel_action_is_consistent(): void
    {
        $order = $this->order('pending', 'processing');

        $this->actingAs($this->admin())
            ->patch(route('admin.orders.cancel', $order))
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertSame('cancelled', $order->payment_status);
    }

    public function test_admin_update_never_edits_the_payment_status(): void
    {
        // the payment status follows Stripe and refunds only (D3)
        $order = $this->order('pending', 'pending');

        $this->actingAs($this->admin())
            ->patch(route('admin.orders.update', $order), ['payment_status' => 'cancelled'])
            ->assertSessionHas('error');

        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_admin_update_rejects_payment_statuses_outside_the_enum(): void
    {
        $order = $this->order('pending', 'pending');

        $this->actingAs($this->admin())
            ->patch(route('admin.orders.update', $order), ['payment_status' => 'refunded'])
            ->assertSessionHas('error');

        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_bulk_cancel_keeps_payment_status_consistent(): void
    {
        $unpaid = $this->order('pending', 'pending');
        $paid = $this->order('processing', 'succeeded');

        app(OrdersController::class)->bulkUpdateStatus(Request::create('/', 'PATCH', [
            'order_ids' => [$unpaid->id, $paid->id],
            'status' => 'cancelled',
        ]));

        $this->assertSame('cancelled', $unpaid->fresh()->payment_status);
        $this->assertSame('succeeded', $paid->fresh()->payment_status);
        $this->assertSame('cancelled', $paid->fresh()->status);
    }
}
