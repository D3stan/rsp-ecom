<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deleting a user must not delete their orders. Two cascades did:
 *  - orders.user_id → users (ON DELETE CASCADE);
 *  - addresses.user_id → users (ON DELETE CASCADE), and orders.billing/shipping_address_id → addresses
 *    (ON DELETE CASCADE): deleting the user deleted their addresses, which deleted the orders using them.
 * Both now set the reference to NULL, so orders and the addresses they ship to survive. The user's
 * address-book entries that no order uses are removed by the User `deleting` event.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('addresses', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
