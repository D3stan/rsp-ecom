<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Idempotency key + audit trail for a transactional email sent for an order.
 * Unique per (order_id, type, fingerprint).
 */
class OrderEmail extends Model
{
    public const TYPE_CONFIRMATION = 'order_confirmation';

    public const TYPE_SHIPPED = 'order_shipped';

    public const TYPE_DELIVERED = 'order_delivered';

    public const TYPE_CANCELLED = 'order_cancelled';

    public const TYPE_REFUNDED = 'order_refunded';

    /** Internal alert to the shop owner (the recipient is the admin address, not the customer). */
    public const TYPE_ADMIN_NEW_ORDER = 'admin_new_order';

    /** Fingerprint for types that can only ever be sent once per order. */
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

    /**
     * Mark a failed attempt. $final = true sets failed_at (no more retries); otherwise only
     * the last error is recorded and the job will retry.
     */
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

    /**
     * Whether this type is addressed to the shop owner instead of the customer.
     */
    public static function isInternal(string $type): bool
    {
        return $type === self::TYPE_ADMIN_NEW_ORDER;
    }

    /**
     * Fingerprint for the "refunded" email: one mail per distinct refunded amount.
     */
    public static function refundedFingerprint(float $amount): string
    {
        return substr(sha1('refunded|'.self::formatAmount($amount)), 0, 40);
    }

    /**
     * Canonical decimal string of a money amount (what the job carries and the fingerprint hashes).
     */
    public static function formatAmount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    /**
     * Fingerprint for the "shipped" email: status + tracking number, so the mail is re-sent
     * only when the tracking number actually changes.
     */
    public static function shippedFingerprint(?string $trackingNumber): string
    {
        return substr(sha1('shipped|'.trim((string) $trackingNumber)), 0, 40);
    }
}
