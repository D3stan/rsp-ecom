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
            // Guest order fields
            $table->string('guest_email')->nullable()->after('user_id');
            $table->string('guest_phone', 20)->nullable()->after('guest_email');
            $table->string('guest_session_id')->nullable()->after('guest_phone');
            
            // Update user_id to be nullable for guest orders
            $table->unsignedBigInteger('user_id')->nullable()->change();
            
            // Add index for guest session lookups
            $table->index('guest_session_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['guest_email', 'guest_phone', 'guest_session_id']);
            $table->dropIndex(['guest_session_id']);
            
            // Note: Cannot revert user_id nullable change safely in down migration
            // as it might contain null values after guest orders are created
        });
    }
};
