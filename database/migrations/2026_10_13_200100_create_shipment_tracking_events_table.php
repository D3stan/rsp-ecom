<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Normalised tracking history of an order's shipment. One row per distinct carrier event:
     * (order_id, provider, event_hash) is unique, so applying the same answer twice
     * (poll + webhook, carrier retries) never duplicates anything. Times are stored in UTC.
     */
    public function up(): void
    {
        Schema::create('shipment_tracking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 50);
            $table->string('tracking_number', 100)->nullable();
            $table->string('status', 30);
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->timestamp('occurred_at');
            $table->string('external_id')->nullable();
            $table->string('event_hash', 64);
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'provider', 'event_hash']);
            $table->index(['order_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_tracking_events');
    }
};
