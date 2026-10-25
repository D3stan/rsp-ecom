<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderEvent extends Model
{
    public const UPDATED_AT = null;

    public const TYPE_STATUS_CHANGED = 'status_changed';

    public const TYPE_PAYMENT_STATUS_CHANGED = 'payment_status_changed';

    public const TYPE_REFUND = 'refund';

    public const TYPE_REFUND_FAILED = 'refund_failed';

    public const TYPE_REFUND_UNCONFIRMED = 'refund_unconfirmed';

    public const TYPE_NOTE = 'note';

    protected $fillable = ['order_id', 'actor_id', 'type', 'from_status', 'to_status', 'payload'];

    protected $casts = [
        'payload' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Shape for the admin order page timeline.
     *
     * @return array<string, mixed>
     */
    public function toTimelineArray(): array
    {
        $payload = $this->payload ?? [];

        return [
            'id' => $this->id,
            'type' => $this->type,
            'from_status' => $this->from_status,
            'to_status' => $this->to_status,
            'reason' => $payload['reason'] ?? null,
            'source' => $payload['meta']['source'] ?? null,
            'payload' => $payload,
            'actor' => $this->actor ? ['id' => $this->actor->id, 'name' => $this->actor->name] : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
