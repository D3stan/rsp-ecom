<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A parcel the admin shipped for an order (App\Shipping\ShippingService). "manual" shipments carry what the admin typed
 * (carrier + tracking number); label drivers also store their external id and the label PDF (private disk).
 */
class Shipment extends Model
{
    public const STATUS_CREATED = 'created';

    /** A draft at a carrier whose API cannot buy it: the owner pays on the carrier's site, then the label is fetched. */
    public const STATUS_AWAITING_PAYMENT = 'awaiting_payment';

    public const STATUS_CANCELLED = 'cancelled';

    /** Not cancelled: the parcel is (or will be) on its way. */
    public const ACTIVE_STATUSES = [self::STATUS_CREATED, self::STATUS_AWAITING_PAYMENT];

    /** Driver name of a shipment typed in by the admin (no label, no carrier API). */
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
