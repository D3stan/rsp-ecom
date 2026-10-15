<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A newsletter: draft -> (scheduled ->) sending -> sent, or cancelled. Sending is done by
 * App\Jobs\Newsletter\DispatchCampaign (recipient snapshot) and SendCampaignEmail (one per recipient).
 */
class NewsletterCampaign extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_SENDING = 'sending';

    public const STATUS_SENT = 'sent';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'subject',
        'preheader',
        'body_markdown',
        'locale',
        'status',
        'scheduled_at',
        'started_at',
        'finished_at',
        'created_by',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'recipients_count' => 'integer',
        'sent_count' => 'integer',
        'failed_count' => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sends(): HasMany
    {
        return $this->hasMany(NewsletterCampaignSend::class, 'campaign_id');
    }

    /** Content can still change: nothing has been sent yet. */
    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_SCHEDULED], true);
    }

    /**
     * Mark a sending campaign as sent once every recipient is done (sent, failed or skipped).
     */
    public function finishIfDone(): void
    {
        $pending = $this->sends()
            ->whereNull('sent_at')
            ->whereNull('failed_at')
            ->whereNull('skipped_at')
            ->exists();

        if ($pending) {
            return;
        }

        static::query()
            ->whereKey($this->id)
            ->where('status', self::STATUS_SENDING)
            ->update(['status' => self::STATUS_SENT, 'finished_at' => now(), 'updated_at' => now()]);
    }

    /**
     * Confirmed subscribers this campaign goes to (all locales when locale is null).
     *
     * @return Builder<NewsletterSubscriber>
     */
    public function audience(): Builder
    {
        return NewsletterSubscriber::query()
            ->where('status', NewsletterSubscriber::STATUS_CONFIRMED)
            ->when($this->locale !== null, fn (Builder $query) => $query->where('locale', $this->locale));
    }
}
