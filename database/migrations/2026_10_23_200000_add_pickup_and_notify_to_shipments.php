<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->date('pickup_date')->nullable()->after('label_path');
            // label purchases defer the shipped email until the carrier first scans the parcel
            $table->boolean('notify_on_pickup')->default(false)->after('pickup_date');
            // was enum('created','cancelled'); a string so new statuses (awaiting_payment) need no migration
            $table->string('status', 30)->default('created')->change();
        });
    }

    /** status stays a string: narrowing it back to the old enum could fail on rows already using awaiting_payment. */
    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn(['pickup_date', 'notify_on_pickup']);
        });
    }
};
