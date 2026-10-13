<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per (order, email type, fingerprint): the idempotency key and the audit trail
     * of every transactional email sent for an order.
     */
    public function up(): void
    {
        Schema::create('order_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('fingerprint', 64);
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'type', 'fingerprint']);
            $table->index(['type', 'sent_at']);
        });

        // Backfill from the legacy orders.confirmation_email_sent* columns (kept for compatibility)
        // so already-notified orders are never mailed again. Chunked and driver-agnostic.
        DB::table('orders')
            ->where('confirmation_email_sent', true)
            ->orderBy('id')
            ->chunkById(500, function ($orders) {
                $now = now();

                $rows = $orders->map(function ($order) use ($now) {
                    $sentAt = $order->confirmation_email_sent_at ?? $order->updated_at ?? $now;

                    return [
                        'order_id' => $order->id,
                        'type' => 'order_confirmation',
                        'fingerprint' => 'default',
                        'queued_at' => $sentAt,
                        'sent_at' => $sentAt,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                })->all();

                DB::table('order_emails')->insertOrIgnore($rows);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_emails');
    }
};
