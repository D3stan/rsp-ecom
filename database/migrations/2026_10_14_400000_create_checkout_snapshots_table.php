<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What the customer was charged for, frozen before the Stripe session is created: the order is built from this
     * (not from the live cart, which the customer can edit while paying). Pruned by model:prune (7 days when never
     * consumed, 90 days once consumed).
     */
    public function up(): void
    {
        Schema::create('checkout_snapshots', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('stripe_session_id')->nullable()->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('guest_session_id')->nullable();
            $table->json('lines');
            $table->decimal('subtotal', 10, 2);
            $table->decimal('shipping', 10, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('tax', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->unsignedInteger('total_cents');
            $table->string('currency', 3)->default('EUR');
            $table->string('coupon_code')->nullable();
            $table->string('cart_hash', 64);
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_snapshots');
    }
};
