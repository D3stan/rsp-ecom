<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Was enum('active','inactive','draft'); a string so new statuses (unlisted) need no migration.
     * NEVER wrap this in DB::transaction: SQLite ignores the foreign_keys pragma inside one and the table
     * rebuild would cascade-delete variants, reviews, wishlists and cart items.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->change();
        });
    }

    /** status stays a string: narrowing it back to the old enum could fail on rows already using unlisted. */
    public function down(): void
    {
        //
    }
};
