<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Feature on home page" toggle for an approved review photo (the home "Rider gallery").
     */
    public function up(): void
    {
        Schema::table('review_media', function (Blueprint $table) {
            $table->boolean('featured')->default(false)->after('status');
            $table->index(['status', 'featured']);
        });
    }

    public function down(): void
    {
        Schema::table('review_media', function (Blueprint $table) {
            $table->dropIndex(['status', 'featured']);
            $table->dropColumn('featured');
        });
    }
};
