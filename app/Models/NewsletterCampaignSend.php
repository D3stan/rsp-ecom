<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsletterCampaignSend extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'campaign_id',
        'subscriber_id',
        'sent_at',
        'failed_at',
        'skipped_at',
        'error',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
        'skipped_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(NewsletterCampaign::class, 'campaign_id');
    }

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(NewsletterSubscriber::class, 'subscriber_id');
    }

    public function isDone(): bool
    {
        return $this->sent_at !== null || $this->failed_at !== null || $this->skipped_at !== null;
    }
}
