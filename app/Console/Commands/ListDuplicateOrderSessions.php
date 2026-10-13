<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ListDuplicateOrderSessions extends Command
{
    protected $signature = 'orders:duplicate-sessions';

    protected $description = 'List Stripe checkout sessions linked to more than one order (read-only). Run before deploying the unique-index migration.';

    public function handle(): int
    {
        $sessionIds = DB::table('orders')
            ->whereNotNull('stripe_checkout_session_id')
            ->groupBy('stripe_checkout_session_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('stripe_checkout_session_id');

        if ($sessionIds->isEmpty()) {
            $this->info('No duplicate stripe_checkout_session_id found: the unique-index migration can run.');

            return self::SUCCESS;
        }

        $rows = Order::whereIn('stripe_checkout_session_id', $sessionIds)
            ->orderBy('stripe_checkout_session_id')
            ->orderBy('id')
            ->get(['id', 'order_number', 'stripe_checkout_session_id', 'status', 'payment_status', 'total_amount', 'created_at'])
            ->map(fn ($order) => [
                $order->stripe_checkout_session_id,
                $order->id,
                $order->order_number,
                $order->status,
                $order->payment_status,
                $order->total_amount,
                $order->created_at,
            ]);

        $this->error($sessionIds->count().' checkout session(s) are linked to more than one order. Review and merge or remove the extra orders manually before migrating (nothing was changed).');
        $this->table(['Session', 'Order ID', 'Order number', 'Status', 'Payment', 'Total', 'Created'], $rows->all());

        return self::FAILURE;
    }
}
