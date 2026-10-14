<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Warnings and safety information shown on the product page (GPSR, Regulation (EU) 2023/988). One language: the
     * catalogue has no per-locale product texts.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->text('safety_information')->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('safety_information');
        });
    }
};
