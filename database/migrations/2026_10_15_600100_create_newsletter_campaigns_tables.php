<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Newsletter campaigns and their per-recipient send log. (campaign_id, subscriber_id) is unique, so snapshotting
     * the recipients twice never adds anyone twice. See App\Jobs\Newsletter\DispatchCampaign.
     */
    public function up(): void
    {
        Schema::create('newsletter_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->string('preheader')->nullable();
            $table->text('body_markdown');
            $table->string('locale', 5)->nullable();
            $table->enum('status', ['draft', 'scheduled', 'sending', 'sent', 'cancelled'])->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('recipients_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
        });

        Schema::create('newsletter_campaign_sends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('newsletter_campaigns')->cascadeOnDelete();
            $table->foreignId('subscriber_id')->constrained('newsletter_subscribers')->cascadeOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            // Not sent because the subscriber was no longer confirmed when its turn came
            $table->timestamp('skipped_at')->nullable();
            $table->text('error')->nullable();

            $table->unique(['campaign_id', 'subscriber_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_campaign_sends');
        Schema::dropIfExists('newsletter_campaigns');
    }
};
