<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A cart line is one variant, not one product: a customer can hold two variants of the same product (e.g. two
 * bulb types). The old (cart_id, product_id) key predates variants and made adding a second variant a 500.
 *
 * The new key is created before the old one is dropped: on MySQL the cart_id foreign key relies on an index
 * leading with cart_id, and dropping the only such index first fails. Rolling back fails while any cart holds
 * two variants of one product.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->unique(['cart_id', 'product_variant_id']);
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropUnique(['cart_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->unique(['cart_id', 'product_id']);
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropUnique(['cart_id', 'product_variant_id']);
        });
    }
};
