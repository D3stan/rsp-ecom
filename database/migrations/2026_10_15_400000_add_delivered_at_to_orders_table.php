<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When the order entered "delivered" (set by Order::transitionTo()); customer service reads the withdrawal window
     * from it.
     *
     * Backfill for orders already delivered: the earliest "delivered" carrier tracking event when there is one,
     * otherwise the order's updated_at (the closest record of the status change these orders have).
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('delivered_at')->nullable();
        });

        DB::table('orders')
            ->where('status', 'delivered')
            ->whereNull('delivered_at')
            ->update([
                'delivered_at' => DB::raw(
                    "COALESCE((SELECT MIN(e.occurred_at) FROM shipment_tracking_events e WHERE e.order_id = orders.id AND e.status = 'delivered'), orders.updated_at)"
                ),
            ]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('delivered_at');
        });
    }
};
