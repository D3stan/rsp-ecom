<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Locale (it|en|de|es|fr) the customer was browsing in when paying; used for order emails.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('locale', 5)->default('it')->after('currency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
    }
};
