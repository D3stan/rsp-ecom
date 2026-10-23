<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The address exactly as the customer typed it: the Stripe metadata only carries a flattened, lossy string
     * (no company, no second line), so the order's address is built from this instead.
     */
    public function up(): void
    {
        Schema::table('checkout_snapshots', function (Blueprint $table) {
            $table->json('shipping_address')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('checkout_snapshots', function (Blueprint $table) {
            $table->dropColumn('shipping_address');
        });
    }
};
