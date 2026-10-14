<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * VAT rate (percent) that applied when the order was placed. orders.tax_amount is the VAT contained in
     * total_amount at that rate. Null on orders created before this column existed (their tax_amount was not
     * populated), so the emails and order pages keep the plain "VAT included" wording for them.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('tax_rate', 5, 2)->nullable()->after('tax_amount');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('tax_rate');
        });
    }
};
