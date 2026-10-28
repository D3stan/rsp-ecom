<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Carrier tracking state of an order (see .ai/rules/shipping.md).
     *
     * tracking_carrier        key of config('shipping.carriers') chosen by the admin
     * tracking_status         last normalised status (App\Shipping\Tracking\Enums\TrackingStatus value)
     * tracking_last_synced_at last time a carrier answer was applied
     * tracking_next_sync_at   next poll is due at/after this moment (null = due now)
     * tracking_started_at     first poll; the polling window (max_age_days) counts from here
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('tracking_carrier', 50)->nullable()->after('tracking_number');
            $table->string('tracking_status', 30)->nullable()->after('tracking_carrier');
            $table->timestamp('tracking_last_synced_at')->nullable()->after('tracking_status');
            $table->timestamp('tracking_next_sync_at')->nullable()->after('tracking_last_synced_at');
            $table->timestamp('tracking_started_at')->nullable()->after('tracking_next_sync_at');

            $table->index('tracking_next_sync_at', 'orders_tracking_next_sync_at_index');
            $table->index('tracking_number', 'orders_tracking_number_index');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_tracking_next_sync_at_index');
            $table->dropIndex('orders_tracking_number_index');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'tracking_carrier',
                'tracking_status',
                'tracking_last_synced_at',
                'tracking_next_sync_at',
                'tracking_started_at',
            ]);
        });
    }
};
