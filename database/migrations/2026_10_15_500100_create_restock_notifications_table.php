<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One row per back-in-stock email (user x variant x restock): the dedupe and cooldown record
     * (App\Listeners\QueueRestockNotifications) and the delivery state (App\Jobs\SendRestockEmail).
     */
    public function up(): void
    {
        Schema::create('restock_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->string('source', 16); // cart | wishlist
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            // Cooldown lookup: has this user been told about this variant recently?
            $table->index(['user_id', 'product_variant_id', 'created_at'], 'restock_notifications_cooldown_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('restock_notifications');
    }
};
