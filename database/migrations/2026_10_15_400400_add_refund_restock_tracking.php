<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Restocking returned items (StockService::restoreItems):
     * - order_items.restocked_qty: units of the line already given back to the stock, so a later cancellation
     *   (restoreForOrder) does not give them back a second time;
     * - stock_restorations: one row per idempotency key (e.g. "refund:{id}"), claimed before the stock moves, so a
     *   retried call restocks once.
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedInteger('restocked_qty')->default(0);
        });

        Schema::create('stock_restorations', function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key')->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->json('quantities');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_restorations');

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('restocked_qty');
        });
    }
};
