<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('stripe_payment_intent_id')->nullable()->after('notes');
            $table->string('stripe_checkout_session_id')->nullable()->after('stripe_payment_intent_id');
            $table->enum('payment_status', ['pending', 'processing', 'succeeded', 'failed', 'cancelled'])
                  ->default('pending')
                  ->after('stripe_checkout_session_id');
            $table->string('payment_method')->nullable()->after('payment_status');
            $table->string('stripe_customer_id')->nullable()->after('payment_method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_payment_intent_id',
                'stripe_checkout_session_id',
                'payment_status',
                'payment_method',
                'stripe_customer_id'
            ]);
        });
    }
};
