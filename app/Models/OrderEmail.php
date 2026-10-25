<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderEmail extends Model
{
    public const TYPE_CONFIRMATION = 'order_confirmation';

    public const TYPE_SHIPPED = 'order_shipped';

    public const TYPE_DELIVERED = 'order_delivered';

    public const TYPE_CANCELLED = 'order_cancelled';

    public const TYPE_REFUNDED = 'order_refunded';

    public const TYPE_REVIEW_REQUEST = 'review_request';

    public const TYPE_ADMIN_NEW_ORDER = 'admin_new_order';

    public const TYPE_ADMIN_OVERSOLD = 'admin_oversold_order';

    public const FINGERPRINT_DEFAULT = 'default';

    protected $fillable = [
        'order_id',
        'type',
        'fingerprint',
        'queued_at',
        'sent_at',
        'failed_at',
        'error',
    ];

    protected $casts = [
        'queued_at' => 'datetime',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isSent(): bool
    {
        return $this->sent_at !== null;
    }

    public function isFailed(): bool
    {
        return $this->sent_at === null && $this->failed_at !== null;
    }

    public function recordFailure(string $error, bool $final): void
    {
        $this->forceFill([
            'error' => mb_substr($error, 0, 2000),
            'failed_at' => $final ? now() : $this->failed_at,
        ])->save();
    }

    public function markSent(): void
    {
        $this->forceFill([
            'sent_at' => now(),
            'failed_at' => null,
            'error' => null,
        ])->save();
    }

    public static function isInternal(string $type): bool
    {
        return in_array($type, [self::TYPE_ADMIN_NEW_ORDER, self::TYPE_ADMIN_OVERSOLD], true);
    }

    public static function refundedFingerprint(float $amount, ?int $refundId = null): string
    {
        $key = $refundId !== null ? 'refunded|refund:'.$refundId : 'refunded|'.self::formatAmount($amount);

        return substr(sha1($key), 0, 40);
    }

    public static function formatAmount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    public static function shippedFingerprint(?string $trackingNumber, ?string $carrier = null): string
    {
        $carrier = mb_strtolower(trim((string) $carrier));

        return substr(sha1('shipped|'.trim((string) $trackingNumber).($carrier === '' ? '' : '|'.$carrier)), 0, 40);
    }
}
