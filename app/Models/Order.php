<?php

namespace App\Models;

use App\Domain\Orders\OrderTransitions;
use App\Exceptions\InvalidOrderTransition;
use App\Shipping\Tracking\DTO\TrackingRequest;
use App\Shipping\Tracking\TrackingManager;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'user_id',
        'billing_address_id',
        'shipping_address_id',
        'status',
        'subtotal',
        'tax_amount',
        'tax_rate',
        'shipping_amount',
        'discount_amount',
        'coupon_code',
        'total_amount',
        'currency',
        'locale',
        'notes',
        'stripe_payment_intent_id',
        'stripe_checkout_session_id',
        'payment_status',
        'payment_method',
        'stripe_customer_id',
        'tracking_number',
        'tracking_carrier',
        'tracking_driver',
        'tracking_status',
        'tracking_last_synced_at',
        'tracking_next_sync_at',
        'tracking_started_at',
        'shipping_method',
        'estimated_delivery_date',
        'guest_email',
        'guest_phone',
        'guest_session_id',
        'confirmation_email_sent',
        'confirmation_email_sent_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'shipping_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'confirmation_email_sent' => 'boolean',
        'confirmation_email_sent_at' => 'datetime',
        'tracking_last_synced_at' => 'datetime',
        'tracking_next_sync_at' => 'datetime',
        'tracking_started_at' => 'datetime',
        'stock_deducted_at' => 'datetime',
        'stock_restored_at' => 'datetime',
        'has_oversold_items' => 'boolean',
        'delivered_at' => 'datetime',
    ];

    protected $hidden = ['stock_deducted_at', 'stock_restored_at', 'has_oversold_items'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            if (empty($order->order_number)) {
                $order->order_number = static::generateOrderNumber();
            }

            if ($order->tracking_carrier !== null) {
                $carrier = trim((string) $order->tracking_carrier);
                $order->tracking_carrier = $carrier !== '' ? $carrier : null;
            }

            if ($order->tracking_driver !== null) {
                $driver = trim((string) $order->tracking_driver);
                $order->tracking_driver = $driver !== '' ? $driver : null;
            }
        });

        static::updating(function ($order) {
            if ($order->isDirty('status')
                && $order->status === 'cancelled'
                && ! $order->isDirty('payment_status')
                && $order->isPaymentPending()) {
                $order->payment_status = 'cancelled';
            }
        });

        static::updating(function ($order) {
            if ($order->isDirty('tracking_carrier')) {
                $carrier = trim((string) $order->tracking_carrier);
                $order->tracking_carrier = $carrier !== '' ? $carrier : null;
            }

            if ($order->isDirty('tracking_driver')) {
                $driver = trim((string) $order->tracking_driver);
                $order->tracking_driver = $driver !== '' ? $driver : null;
            }

            if (! $order->isDirty('tracking_number') && ! $order->isDirty('tracking_carrier') && ! $order->isDirty('tracking_driver')) {
                return;
            }

            $numberChanged = strcasecmp(
                TrackingRequest::normalizeNumber($order->getOriginal('tracking_number')),
                TrackingRequest::normalizeNumber($order->tracking_number),
            ) !== 0;

            $carrierChanged = strcasecmp(
                trim((string) $order->getOriginal('tracking_carrier')),
                trim((string) $order->tracking_carrier),
            ) !== 0;

            $driverChanged = strcasecmp(
                trim((string) $order->getOriginal('tracking_driver')),
                trim((string) $order->tracking_driver),
            ) !== 0;

            if ($numberChanged || $carrierChanged || $driverChanged) {
                $order->tracking_status = null;
                $order->tracking_last_synced_at = null;
                $order->tracking_next_sync_at = null;
                $order->tracking_started_at = null;
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function billingAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'billing_address_id');
    }

    public function shippingAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'shipping_address_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(Size::class);
    }

    public function trackingEvents(): HasMany
    {
        return $this->hasMany(ShipmentTrackingEvent::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class)->orderBy('id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class)->orderBy('created_at')->orderBy('id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    public function scopeShipped($query)
    {
        return $query->where('status', 'shipped');
    }

    public function scopeDelivered($query)
    {
        return $query->where('status', 'delivered');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    public function scopePaymentPending($query)
    {
        return $query->where('payment_status', 'pending');
    }

    public function scopePaymentProcessing($query)
    {
        return $query->where('payment_status', 'processing');
    }

    public function scopePaymentSucceeded($query)
    {
        return $query->where('payment_status', 'succeeded');
    }

    public function scopePaymentFailed($query)
    {
        return $query->where('payment_status', 'failed');
    }

    public function scopeRefundDue($query)
    {
        return $query->where('status', 'cancelled')->whereIn('payment_status', self::REFUND_DUE_PAYMENT_STATUSES);
    }

    public function isRefundDue(): bool
    {
        return $this->status === 'cancelled' && in_array($this->payment_status, self::REFUND_DUE_PAYMENT_STATUSES, true);
    }

    public function getTotalItemsAttribute(): int
    {
        return $this->orderItems->sum('quantity');
    }

    public function getIsCompleteAttribute(): bool
    {
        return $this->status === 'delivered';
    }

    public function getIsCancelledAttribute(): bool
    {
        return $this->status === 'cancelled';
    }

    public function getCanBeCancelledAttribute(): bool
    {
        return OrderTransitions::allows($this->status, 'cancelled');
    }

    /**
     * @return list<string> the statuses this order may move to next (OrderTransitions)
     */
    public function allowedTransitions(): array
    {
        $allowed = OrderTransitions::allowedFrom($this->status);

        return array_values(array_filter($allowed, fn (string $to) => $to !== 'shipped' || $this->canShip()));
    }

    public function canShip(): bool
    {
        return $this->status === 'processing' && $this->hasShippablePayment();
    }

    public function hasShippablePayment(): bool
    {
        return in_array($this->payment_status, self::SHIPPABLE_PAYMENT_STATUSES, true);
    }

    /**
     * The one way to change the status. Checks the move against OrderTransitions on the stored (locked) status, so two
     * admins, or an admin and the tracking sync, cannot both move the order from the same starting point. Sets
     * delivered_at when the order enters "delivered" and saves the model, together with any other attribute the caller
     * changed on it before. The OrderObserver then reacts as for any save (stock restore on cancel, status emails).
     * Each actual move writes one order_events row (actor, both statuses, reason and $meta in payload).
     *
     * Moving to the status the order already has is a no-op for the status (other pending changes are still saved).
     *
     * @param  array<string, mixed>  $meta  context of the change (source, ids) for the audit trail
     *
     * @throws InvalidOrderTransition when the lifecycle does not allow the move, or it needs a reason and has none
     */
    public function transitionTo(string $to, ?User $actor = null, ?string $reason = null, array $meta = []): void
    {
        if (! $this->exists) {
            throw new \LogicException('Only a saved order can change status.');
        }

        DB::transaction(function () use ($to, $actor, $reason, $meta) {
            $row = static::query()->whereKey($this->getKey())->lockForUpdate()->first(['status', 'payment_status']);
            $stored = $row?->getAttribute('status');

            if ($stored !== null && $stored !== $this->getOriginal('status')) {
                $this->setAttribute('status', $stored);
                $this->syncOriginalAttribute('status');
            }

            if ($row !== null && ! $this->isDirty('payment_status') && $row->getAttribute('payment_status') !== $this->getOriginal('payment_status')) {
                $this->setAttribute('payment_status', $row->getAttribute('payment_status'));
                $this->syncOriginalAttribute('payment_status');
            }

            $from = $this->getOriginal('status');
            $reason = trim((string) $reason) !== '' ? trim((string) $reason) : null;

            if ($from === $to) {
                if ($this->isDirty()) {
                    $this->save();
                }

                return;
            }

            if (! OrderTransitions::allows($from, $to)) {
                throw InvalidOrderTransition::notAllowed((string) $this->order_number, $from, $to);
            }

            if ($reason === null && OrderTransitions::requiresReason($from, $to)) {
                throw InvalidOrderTransition::reasonRequired((string) $this->order_number, $from, $to);
            }

            if ($to === 'shipped' && ! $this->hasShippablePayment()) {
                throw InvalidOrderTransition::notPaid((string) $this->order_number, (string) $this->payment_status);
            }

            if ($from === 'shipped' && $to === 'processing') {
                $meta['revoked_tracking'] = [
                    'tracking_number' => $this->tracking_number,
                    'tracking_carrier' => $this->tracking_carrier,
                    'tracking_driver' => $this->tracking_driver,
                ];

                $this->tracking_number = null;
                $this->tracking_carrier = null;
                $this->tracking_driver = null;
                $this->tracking_status = null;
                $this->tracking_last_synced_at = null;
                $this->tracking_next_sync_at = null;
                $this->tracking_started_at = null;

                $this->shipments()->whereIn('status', Shipment::ACTIVE_STATUSES)->update(['status' => Shipment::STATUS_CANCELLED]);
                $this->unsetRelation('shipments');
            }

            if ($from === 'processing' && $to === 'cancelled') {
                $this->shipments()->whereIn('status', Shipment::ACTIVE_STATUSES)->update(['status' => Shipment::STATUS_CANCELLED]);
                $this->unsetRelation('shipments');
            }

            $this->status = $to;

            if ($to === 'delivered') {
                $this->delivered_at = now();
            }

            $this->save();

            $this->events()->create([
                'actor_id' => $actor?->getKey(),
                'type' => OrderEvent::TYPE_STATUS_CHANGED,
                'from_status' => $from,
                'to_status' => $to,
                'payload' => array_filter(['reason' => $reason, 'meta' => $meta]) ?: null,
            ]);
        });
    }

    public function cancel(?User $actor = null, ?string $reason = null): bool
    {
        if (! $this->can_be_cancelled) {
            return false;
        }

        try {
            $this->transitionTo('cancelled', $actor, $reason);
        } catch (InvalidOrderTransition) {
            return false;
        }

        return true;
    }

    public function markAsProcessing(?User $actor = null): bool
    {
        $this->transitionTo('processing', $actor);

        return true;
    }

    public function markAsShipped(?User $actor = null): bool
    {
        $this->transitionTo('shipped', $actor);

        return true;
    }

    public function markAsDelivered(?User $actor = null): bool
    {
        $this->transitionTo('delivered', $actor);

        return true;
    }

    public static function generateOrderNumber(): string
    {
        do {
            $orderNumber = 'ORD-'.date('Y').'-'.str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
        } while (static::where('order_number', $orderNumber)->exists());

        return $orderNumber;
    }

    public function hasStripePaymentIntent(): bool
    {
        return ! empty($this->stripe_payment_intent_id);
    }

    public function hasStripeCheckoutSession(): bool
    {
        return ! empty($this->stripe_checkout_session_id);
    }

    public function isPaymentCompleted(): bool
    {
        return $this->payment_status === 'succeeded';
    }

    public function isPaymentPending(): bool
    {
        return in_array($this->payment_status, ['pending', 'processing']);
    }

    public const PAYMENT_STATUSES = ['pending', 'processing', 'succeeded', 'failed', 'cancelled', 'refunded', 'partially_refunded'];

    public const CAPTURED_PAYMENT_STATUSES = ['succeeded', 'partially_refunded', 'refunded'];

    public const REFUND_DUE_PAYMENT_STATUSES = ['succeeded', 'partially_refunded'];

    public const SHIPPABLE_PAYMENT_STATUSES = ['succeeded', 'partially_refunded'];

    public function hasCapturedPayment(): bool
    {
        return in_array($this->payment_status, self::CAPTURED_PAYMENT_STATUSES, true);
    }

    public function financialsLocked(): bool
    {
        return $this->payment_status === 'processing'
            || $this->hasCapturedPayment()
            || in_array($this->status, ['shipped', 'delivered'], true);
    }

    public function isPaymentFailed(): bool
    {
        return $this->payment_status === 'failed';
    }

    public function updatePaymentStatus(string $status, ?string $paymentMethod = null): bool
    {
        $this->payment_status = $status;
        if ($paymentMethod) {
            $this->payment_method = $paymentMethod;
        }

        return $this->save();
    }

    public static function createFromCart(Cart $cart, Address $billingAddress, Address $shippingAddress, array $additionalData = []): self
    {
        $subtotal = $cart->subtotal;
        $shippingCost = $cart->shipping_cost;
        $taxAmount = $additionalData['tax_amount'] ?? 0;
        $total = $subtotal + $shippingCost + $taxAmount;

        $order = static::create([
            'user_id' => $cart->user_id,
            'billing_address_id' => $billingAddress->id,
            'shipping_address_id' => $shippingAddress->id,
            'status' => 'pending',
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'shipping_amount' => $shippingCost,
            'total_amount' => $total,
            'currency' => $additionalData['currency'] ?? 'EUR',
            'notes' => $additionalData['notes'] ?? null,
        ]);

        foreach ($cart->cartItems as $cartItem) {
            $order->orderItems()->create([
                'product_id' => $cartItem->product_id,
                'product_name' => $cartItem->product->name,
                'quantity' => $cartItem->quantity,
                'price' => $cartItem->price,
                'total' => $cartItem->total,
            ]);
        }

        return $order;
    }

    public function isGuestOrder(): bool
    {
        return $this->user_id === null && $this->guest_email !== null;
    }

    public function getCustomerEmail(): ?string
    {
        return $this->user?->email ?? $this->guest_email;
    }

    public function getCustomerName(): ?string
    {
        if ($this->user) {
            return $this->user->name;
        }

        if ($this->billingAddress) {
            return trim($this->billingAddress->first_name.' '.$this->billingAddress->last_name);
        }

        return null;
    }

    public function publicUrl(): string
    {
        return URL::temporarySignedRoute('orders.guest.show', now()->addDays(static::publicUrlDays()), ['order' => $this->order_number]);
    }

    public static function publicUrlDays(): int
    {
        return max(1, (int) settings('orders.public_link_days', 30));
    }

    public function trackingUrl(?string $trackingNumber = null): ?string
    {
        $number = trim((string) ($trackingNumber ?? $this->tracking_number));
        $carrier = $this->getAttribute('tracking_carrier');
        $driver = $this->getAttribute('tracking_driver');

        if ($number === '') {
            return null;
        }

        $url = app(TrackingManager::class)->trackingUrl(
            $number,
            is_string($carrier) && $carrier !== '' ? $carrier : null,
            is_string($driver) && $driver !== '' ? $driver : null,
        );

        if ($url !== null) {
            return $url;
        }

        if ($this->exists) {
            $shipments = $this->relationLoaded('shipments')
                ? $this->shipments
                : $this->shipments()->where('status', Shipment::STATUS_CREATED)->get();

            foreach ($shipments as $shipment) {
                if ($shipment->status !== Shipment::STATUS_CREATED || $shipment->tracking_number !== $number) {
                    continue;
                }

                $payload = $shipment->request_payload;
                if (! is_array($payload)) {
                    continue;
                }

                $trackingUrl = $payload['url_tracking'] ?? null;
                if (is_string($trackingUrl)
                    && (str_starts_with($trackingUrl, 'http://') || str_starts_with($trackingUrl, 'https://'))
                    && filter_var($trackingUrl, FILTER_VALIDATE_URL) !== false
                ) {
                    return $trackingUrl;
                }
            }

            foreach ($shipments as $shipment) {
                if ($shipment->status !== Shipment::STATUS_CREATED) {
                    continue;
                }

                $payload = $shipment->request_payload;
                if (! is_array($payload)) {
                    continue;
                }

                $trackingUrl = $payload['url_tracking'] ?? null;
                if (is_string($trackingUrl)
                    && (str_starts_with($trackingUrl, 'http://') || str_starts_with($trackingUrl, 'https://'))
                    && filter_var($trackingUrl, FILTER_VALIDATE_URL) !== false
                ) {
                    return $trackingUrl;
                }
            }
        }

        return null;
    }

    public function trackingCarrierName(): ?string
    {
        $carrier = $this->getAttribute('tracking_carrier');

        return is_string($carrier) && $carrier !== '' ? app(TrackingManager::class)->carrierName($carrier) : null;
    }

    public function isOwnedBy(?Authenticatable $user, ?string $sessionId): bool
    {
        if ($user !== null && $this->user_id !== null && (int) $user->getAuthIdentifier() === (int) $this->user_id) {
            return true;
        }

        return $this->user_id === null
            && ! empty($this->guest_session_id)
            && ! empty($sessionId)
            && hash_equals((string) $this->guest_session_id, $sessionId);
    }

    public function scopeGuestOrders($query)
    {
        return $query->whereNull('user_id')->whereNotNull('guest_email');
    }

    public function scopeUserOrders($query)
    {
        return $query->whereNotNull('user_id');
    }

    public function scopeByGuestSession($query, string $sessionId)
    {
        return $query->where('guest_session_id', $sessionId);
    }

    public function markEmailAsSent(): bool
    {
        return $this->update([
            'confirmation_email_sent' => true,
            'confirmation_email_sent_at' => now(),
        ]);
    }

    public function hasEmailBeenSent(): bool
    {
        return (bool) $this->confirmation_email_sent
            || $this->emails()
                ->where('type', OrderEmail::TYPE_CONFIRMATION)
                ->whereNotNull('sent_at')
                ->exists();
    }

    public function emails(): HasMany
    {
        return $this->hasMany(OrderEmail::class);
    }

    public const CUSTOMER_FIELDS = [
        'id', 'order_number', 'status', 'payment_status', 'created_at', 'currency',
        'subtotal', 'tax_amount', 'tax_rate', 'shipping_amount', 'discount_amount', 'total_amount',
        'tracking_number', 'tracking_carrier', 'tracking_status', 'tracking_last_synced_at',
    ];

    public function toCustomerArray(): array
    {
        return Arr::only($this->attributesToArray(), self::CUSTOMER_FIELDS);
    }

    /**
     * Customer-facing shipment tracking: the public tracking link, the carrier display name, and the checkpoint
     * list a customer may see (status + description + location + time, no raw carrier codes, no provider/hashes).
     * Merged into the customer order payloads (account orders, dashboard, guest page). trackingEvents must be
     * eager-loaded by the caller; when it is not, no event query is run and the list is empty.
     *
     * @return array<string, mixed>
     */
    public function customerTrackingArray(): array
    {
        if ($this->status !== 'shipped' && $this->status !== 'delivered') {
            return [
                'tracking_url' => null,
                'carrier_name' => null,
                'tracking_events' => [],
            ];
        }

        $events = [];

        if ($this->relationLoaded('trackingEvents')) {
            $trackingCollection = $this->trackingEvents;

            if ($this->tracking_number !== null && $this->tracking_number !== '') {
                $currentNormalized = TrackingRequest::normalizeNumber($this->tracking_number);
                $trackingCollection = $trackingCollection->filter(
                    fn (ShipmentTrackingEvent $event) => TrackingRequest::normalizeNumber($event->tracking_number) === $currentNormalized
                );
            }

            $events = $trackingCollection
                ->sortByDesc('occurred_at')
                ->values()
                ->map(fn (ShipmentTrackingEvent $event) => [
                    'status' => $event->status,
                    'description' => $event->description,
                    'location' => $event->location,
                    'occurred_at' => $event->occurred_at?->toISOString(),
                ])
                ->all();
        }

        return [
            'tracking_url' => $this->trackingUrl(),
            'carrier_name' => $this->trackingCarrierName(),
            'tracking_events' => $events,
        ];
    }

    public function toFrontendArray(): array
    {
        $data = $this->toArray();

        $data['subtotal'] = (float) $this->subtotal;
        $data['tax_amount'] = (float) $this->tax_amount;
        $data['tax_rate'] = $this->tax_rate !== null ? (float) $this->tax_rate : null;
        $data['shipping_amount'] = (float) $this->shipping_amount;
        $data['discount_amount'] = (float) $this->discount_amount;
        $data['total_amount'] = (float) $this->total_amount;

        if ($this->relationLoaded('orderItems')) {
            $data['orderItems'] = $this->orderItems->map(function ($item) {
                $product = $item->product_id ? $item->product : null;

                return [
                    'id' => $item->id,
                    'product' => [
                        'id' => $item->product_id ?? 'unknown',
                        'name' => $item->product_name ?? ($product ? $product->name : 'Unknown Product'),
                        'price' => (float) $item->price,
                        'image_url' => $product ? $product->image_url : null,
                    ],
                    'size' => $item->size ? ['id' => $item->size->id, 'name' => $item->size->name] : null,
                    'variant' => $item->productVariant ? ['id' => $item->productVariant->id, 'name' => $item->productVariant->name] : null,
                    'quantity' => $item->quantity,
                    'price' => (float) $item->price,
                    'total' => (float) $item->total,
                ];
            })->toArray();
        } else {
            $data['orderItems'] = [];
        }

        if ($this->relationLoaded('billingAddress')) {
            $data['billingAddress'] = $this->billingAddress ? $this->billingAddress->toArray() : null;
        }

        if ($this->relationLoaded('shippingAddress')) {
            $data['shippingAddress'] = $this->shippingAddress ? $this->shippingAddress->toArray() : null;
        }

        if ($this->relationLoaded('user')) {
            $data['user'] = $this->user ? $this->user->toArray() : null;
        }

        return $data;
    }
}
