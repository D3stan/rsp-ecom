<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * products.category_id and products.size_id were ON DELETE CASCADE: deleting a category or a shipping size
 * deleted its products (and with them variants, reviews, wishlists and cart lines). Only the admin controller
 * stopped it. The database now refuses such a delete (RESTRICT); the controller keeps its friendly message.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropForeign(['size_id']);
            $table->foreign('category_id')->references('id')->on('categories')->restrictOnDelete();
            $table->foreign('size_id')->references('id')->on('sizes')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropForeign(['size_id']);
            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
            $table->foreign('size_id')->references('id')->on('sizes')->cascadeOnDelete();
        });
    }
};
