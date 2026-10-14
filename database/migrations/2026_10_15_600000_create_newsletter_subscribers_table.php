<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Newsletter subscribers (one row per lowercased email) and their consent trail.
     * See App\Services\Newsletter\MarketingConsent.
     */
    public function up(): void
    {
        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('locale', 5)->nullable();
            $table->enum('status', ['pending', 'confirmed', 'unsubscribed'])->default('pending');
            $table->string('confirm_token_hash', 64)->nullable()->unique();
            $table->timestamp('confirmation_sent_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->enum('source', ['footer', 'register', 'checkout', 'account', 'admin']);
            $table->timestamps();

            $table->index(['status', 'locale']);
        });

        // Proof of consent: append-only. subscriber_id is nullable so the anonymised "erased" event can outlive
        // the subscriber it was about (GDPR erasure deletes the row and, through the cascade, its earlier events).
        Schema::create('consent_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscriber_id')->nullable()->constrained('newsletter_subscribers')->cascadeOnDelete();
            $table->enum('event', ['requested', 'confirmed', 'unsubscribed', 'resubscribed', 'erased']);
            $table->string('source', 40);
            $table->string('consent_text_version', 100);
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['subscriber_id', 'event']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_events');
        Schema::dropIfExists('newsletter_subscribers');
    }
};
