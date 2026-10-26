<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-order tracking driver (App\Shipping\Tracking\TrackingManager). Null falls back to the
     * configured default tracking driver; 'manual' disables API polling and uses URL templates only;
     * any registered provider name routes polling, tracking URLs, and webhooks to that provider.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('tracking_driver', 50)->nullable()->after('tracking_carrier');
            $table->index('tracking_driver', 'orders_tracking_driver_index');
        });

        // Backfill from the latest created API label shipment (non-manual) so existing label orders
        // keep their provider. Existing manual shipments stay null to continue using the default driver.
        if (Schema::hasTable('shipments')) {
            $latestShipments = DB::table('shipments')
                ->select('order_id', 'driver')
                ->where('status', 'created')
                ->orderByDesc('id')
                ->get();

            $seenOrders = [];

            foreach ($latestShipments as $shipment) {
                if (isset($seenOrders[$shipment->order_id])) {
                    continue;
                }

                $seenOrders[$shipment->order_id] = true;

                if (! empty($shipment->driver) && $shipment->driver !== 'manual') {
                    DB::table('orders')
                        ->where('id', $shipment->order_id)
                        ->whereNull('tracking_driver')
                        ->update(['tracking_driver' => $shipment->driver]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_tracking_driver_index');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('tracking_driver');
        });
    }
};
