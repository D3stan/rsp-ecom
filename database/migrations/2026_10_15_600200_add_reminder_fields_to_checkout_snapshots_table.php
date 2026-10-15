<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Abandoned-checkout reminder (docs/newsletter-and-reminders.md): who may be reminded (only with the marketing
     * consent ticked at checkout), in which language, and whether the one reminder was sent / the cart recovered.
     */
    public function up(): void
    {
        Schema::table('checkout_snapshots', function (Blueprint $table) {
            $table->string('email')->nullable();
            $table->string('locale', 5)->nullable();
            $table->boolean('marketing_consent')->default(false);
            $table->string('consent_ip', 45)->nullable();
            $table->timestamp('reminder_sent_at')->nullable()->index();
            $table->timestamp('recovered_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('checkout_snapshots', function (Blueprint $table) {
            $table->dropIndex(['reminder_sent_at']);
            $table->dropColumn(['email', 'locale', 'marketing_consent', 'consent_ip', 'reminder_sent_at', 'recovered_at']);
        });
    }
};
