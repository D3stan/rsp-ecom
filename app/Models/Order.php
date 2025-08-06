<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

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
        'shipping_amount',
        'total_amount',
        'currency',
        'notes',
        'stripe_payment_intent_id',
        'stripe_checkout_session_id',
        'payment_status',
        'payment_method',
        'stripe_customer_id',
        // Guest order fields
        'guest_email',
        'guest_phone',
        'guest_session_id',
        // Email tracking
        'confirmation_email_sent',
        'confirmation_email_sent_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'shipping_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'confirmation_email_sent' => 'boolean',
        'confirmation_email_sent_at' => 'datetime',
    ];

    // Boot method to generate order number
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            if (empty($order->order_number)) {
                $order->order_number = static::generateOrderNumber();
            }
        });
    }

    // Relationships
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

    // Scopes
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

    // Payment status scopes
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

    // Accessors
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
        return in_array($this->status, ['pending', 'processing']);
    }

    // Helper methods
    public function cancel(): bool
    {
        if (!$this->can_be_cancelled) {
            return false;
        }

        return $this->update(['status' => 'cancelled']);
    }

    public function markAsProcessing(): bool
    {
        return $this->update(['status' => 'processing']);
    }

    public function markAsShipped(): bool
    {
        return $this->update(['status' => 'shipped']);
    }

    public function markAsDelivered(): bool
    {
        return $this->update(['status' => 'delivered']);
    }

    // Generate unique order number
    public static function generateOrderNumber(): string
    {
        do {
            $orderNumber = 'ORD-' . date('Y') . '-' . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
        } while (static::where('order_number', $orderNumber)->exists());

        return $orderNumber;
    }

    // Payment helper methods
    public function hasStripePaymentIntent(): bool
    {
        return !empty($this->stripe_payment_intent_id);
    }

    public function hasStripeCheckoutSession(): bool
    {
        return !empty($this->stripe_checkout_session_id);
    }

    public function isPaymentCompleted(): bool
    {
        return $this->payment_status === 'succeeded';
    }

    public function isPaymentPending(): bool
    {
        return in_array($this->payment_status, ['pending', 'processing']);
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

    // Create order from cart
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
            'currency' => $additionalData['currency'] ?? 'USD',
            'notes' => $additionalData['notes'] ?? null,
        ]);

        // Create order items from cart items
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

    // Guest order methods
    public function isGuestOrder(): bool
    {
        return $this->user_id === null && $this->guest_email !== null;
    }

    public function getCustomerEmail(): ?string
    {
        return $this->user_id ? $this->user->email : $this->guest_email;
    }

    public function getCustomerName(): ?string
    {
        if ($this->user_id) {
            return $this->user->name;
        }
        
        // For guest orders, try to get name from billing address
        if ($this->billingAddress) {
            return trim($this->billingAddress->first_name . ' ' . $this->billingAddress->last_name);
        }
        
        return null;
    }

    // Scopes for guest orders
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

    /**
     * Mark confirmation email as sent
     */
    public function markEmailAsSent(): bool
    {
        return $this->update([
            'confirmation_email_sent' => true,
            'confirmation_email_sent_at' => now(),
        ]);
    }

    /**
     * Check if confirmation email has been sent
     */
    public function hasEmailBeenSent(): bool
    {
        return $this->confirmation_email_sent;
    }
}
