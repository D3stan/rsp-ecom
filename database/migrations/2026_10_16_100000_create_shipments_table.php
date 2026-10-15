<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per parcel the admin shipped (App\Shipping\ShippingService). The order keeps tracking_number /
     * tracking_carrier as the current values (tracking module, emails); this table is the history, and holds what a
     * label driver returned (external id, label PDF on the private disk).
     */
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('carrier_code', 50);
            $table->string('driver', 50);
            $table->string('external_id')->nullable();
            $table->string('tracking_number', 100)->nullable();
            $table->string('label_path')->nullable();
            $table->enum('status', ['created', 'cancelled'])->default('created');
            $table->json('request_payload')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
