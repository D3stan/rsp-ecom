<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One Stripe checkout session must map to exactly one order. Existing data is never
     * touched: if duplicates are already present the migration aborts so they can be
     * reviewed and merged by hand. NULLs are allowed multiple times by sqlite/mysql/pgsql.
     */
    public function up(): void
    {
        $duplicates = DB::table('orders')
            ->select('stripe_checkout_session_id', DB::raw('COUNT(*) as orders_count'))
            ->whereNotNull('stripe_checkout_session_id')
            ->groupBy('stripe_checkout_session_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isNotEmpty()) {
            $list = $duplicates
                ->take(20)
                ->map(fn ($row) => "{$row->stripe_checkout_session_id} ({$row->orders_count} orders)")
                ->implode(', ');

            throw new RuntimeException(
                'Cannot add a unique index on orders.stripe_checkout_session_id: '
                .$duplicates->count().' checkout session(s) are linked to more than one order. '
                .'Review and merge or remove the duplicate orders manually, then run the migration again. '
                .'No data was changed. Affected sessions: '.$list
            );
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->unique('stripe_checkout_session_id', 'orders_stripe_checkout_session_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique('orders_stripe_checkout_session_id_unique');
        });
    }
};
