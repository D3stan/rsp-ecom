<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    public const SOURCE_ADMIN = 'admin';

    public const SOURCE_STRIPE_DASHBOARD = 'stripe_dashboard';

    protected $fillable = [
        'order_id', 'amount_cents', 'currency', 'reason', 'status', 'stripe_refund_id', 'failure_message', 'source', 'created_by', 'restock',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'restock' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function amount(): float
    {
        return $this->amount_cents / 100;
    }

    /**
     * Shape for the admin order page.
     *
     * @return array<string, mixed>
     */
    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'amount_cents' => $this->amount_cents,
            'currency' => $this->currency,
            'reason' => $this->reason,
            'status' => $this->status,
            'source' => $this->source,
            'failure_message' => $this->failure_message,
            'stripe_refund_id' => $this->stripe_refund_id,
            'created_by' => $this->creator ? ['id' => $this->creator->id, 'name' => $this->creator->name] : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
