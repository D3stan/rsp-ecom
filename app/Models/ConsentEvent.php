<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only proof of marketing consent: what happened, when, from where (IP, user agent), through which
 * entry point, and which consent text the person saw (consent_text_version is the lang key of that text).
 */
class ConsentEvent extends Model
{
    public const EVENT_REQUESTED = 'requested';

    public const EVENT_CONFIRMED = 'confirmed';

    public const EVENT_UNSUBSCRIBED = 'unsubscribed';

    public const EVENT_RESUBSCRIBED = 'resubscribed';

    public const EVENT_ERASED = 'erased';

    public const UPDATED_AT = null;

    protected $fillable = [
        'subscriber_id',
        'event',
        'source',
        'consent_text_version',
        'ip',
        'user_agent',
    ];

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(NewsletterSubscriber::class, 'subscriber_id');
    }
}
