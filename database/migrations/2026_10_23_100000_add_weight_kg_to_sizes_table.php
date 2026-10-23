<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Packed weight of a box size (box + typical contents), used to prefill label parcels.
     * Null falls back to the shipping.default_parcel_weight_kg setting.
     */
    public function up(): void
    {
        Schema::table('sizes', function (Blueprint $table) {
            $table->decimal('weight_kg', 8, 3)->nullable()->after('height');
        });
    }

    public function down(): void
    {
        Schema::table('sizes', function (Blueprint $table) {
            $table->dropColumn('weight_kg');
        });
    }
};
