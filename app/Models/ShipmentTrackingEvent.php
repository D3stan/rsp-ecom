<?php

namespace App\Models;

use App\Shipping\Tracking\Enums\TrackingStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentTrackingEvent extends Model
{
    protected $fillable = [
        'order_id',
        'provider',
        'tracking_number',
        'status',
        'description',
        'location',
        'occurred_at',
        'external_id',
        'event_hash',
        'raw',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'raw' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function trackingStatus(): TrackingStatus
    {
        return TrackingStatus::fromValue($this->status);
    }
}
