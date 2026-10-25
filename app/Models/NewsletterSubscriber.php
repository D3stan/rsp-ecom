<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\URL;

class NewsletterSubscriber extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_UNSUBSCRIBED = 'unsubscribed';

    public const SOURCES = ['footer', 'register', 'checkout', 'account', 'admin'];

    protected $fillable = [
        'email',
        'user_id',
        'locale',
        'status',
        'confirm_token_hash',
        'confirmation_sent_at',
        'confirmed_at',
        'unsubscribed_at',
        'source',
    ];

    protected $hidden = [
        'confirm_token_hash',
    ];

    protected $casts = [
        'confirmation_sent_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function consentEvents(): HasMany
    {
        return $this->hasMany(ConsentEvent::class, 'subscriber_id');
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function unsubscribeUrl(): string
    {
        return URL::signedRoute('newsletter.unsubscribe', ['subscriber' => $this->id]);
    }

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
