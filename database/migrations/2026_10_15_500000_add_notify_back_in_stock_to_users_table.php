<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Whether the user gets an email when an item of their cart or wishlist is back in stock. A service notification
     * about items the user saved, with an opt-out (account settings, one-click link in every email). If it has to
     * become opt-in, change the default to false (and the account copy).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('notify_back_in_stock')->default(true)->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notify_back_in_stock');
        });
    }
};
