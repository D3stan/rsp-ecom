<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const BEFORE = ['pending', 'processing', 'succeeded', 'failed', 'cancelled'];

    private const AFTER = ['pending', 'processing', 'succeeded', 'failed', 'cancelled', 'refunded', 'partially_refunded'];

    /**
     * Refunds (App\Services\RefundService) move a captured payment to "partially_refunded" or "refunded". The column is
     * an enum: MySQL changes it in place, SQLite rebuilds the table (Laravel's native column change).
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('payment_status', self::AFTER)->default('pending')->change();
        });
    }

    /**
     * Refunded orders fall back to "succeeded" (the closest earlier state: money was captured); the refunds table
     * still records what was returned.
     */
    public function down(): void
    {
        DB::table('orders')->whereIn('payment_status', ['refunded', 'partially_refunded'])->update(['payment_status' => 'succeeded']);

        Schema::table('orders', function (Blueprint $table) {
            $table->enum('payment_status', self::BEFORE)->default('pending')->change();
        });
    }
};
