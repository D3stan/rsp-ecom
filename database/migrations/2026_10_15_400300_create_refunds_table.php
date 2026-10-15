<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Money returned for an order (App\Services\RefundService): issued by an admin, or found on Stripe for a refund made
     * in the Stripe dashboard (charge.refunded webhook). Amounts in integer cents. A pending row reserves its amount
     * while Stripe is called; stripe_refund_id is unique so a refund is never recorded twice.
     */
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            // Financial record: an order with refunds cannot be deleted by accident
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('amount_cents');
            $table->char('currency', 3);
            $table->string('reason', 500)->nullable();
            $table->enum('status', ['pending', 'succeeded', 'failed'])->default('pending');
            $table->string('stripe_refund_id')->nullable()->unique();
            $table->text('failure_message')->nullable();
            $table->enum('source', ['admin', 'stripe_dashboard'])->default('admin');
            // units to put back in stock once the refund succeeds ({order_item_id: qty}); kept for retries and webhooks
            $table->json('restock')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
