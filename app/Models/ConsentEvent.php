<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
