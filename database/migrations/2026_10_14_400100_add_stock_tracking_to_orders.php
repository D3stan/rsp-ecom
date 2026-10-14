<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stock bookkeeping of an order: when its quantities were taken from the variants and when they were given
     * back (so neither happens twice), whether the shop sold more than it had, and by how many units per line.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('stock_deducted_at')->nullable();
            $table->timestamp('stock_restored_at')->nullable();
            $table->boolean('has_oversold_items')->default(false);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedInteger('oversold_qty')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('oversold_qty');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['stock_deducted_at', 'stock_restored_at', 'has_oversold_items']);
        });
    }
};
