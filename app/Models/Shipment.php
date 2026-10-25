<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shipment extends Model
{
    public const STATUS_CREATED = 'created';

    public const STATUS_AWAITING_PAYMENT = 'awaiting_payment';

    public const STATUS_CANCELLED = 'cancelled';

    public const ACTIVE_STATUSES = [self::STATUS_CREATED, self::STATUS_AWAITING_PAYMENT];

    public const DRIVER_MANUAL = 'manual';

    protected $fillable = [
        'order_id', 'carrier_code', 'driver', 'external_id', 'tracking_number', 'label_path', 'pickup_date', 'notify_on_pickup', 'status', 'request_payload', 'created_by',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'pickup_date' => 'date',
        'notify_on_pickup' => 'boolean',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isManual(): bool
    {
        return $this->driver === self::DRIVER_MANUAL;
    }
}
