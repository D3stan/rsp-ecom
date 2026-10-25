<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckoutSnapshot extends Model
{
    use HasUlids, MassPrunable;

    public const PRUNE_UNCONSUMED_AFTER_DAYS = 7;

    public const PRUNE_CONSUMED_AFTER_DAYS = 90;

    protected $fillable = [
        'stripe_session_id',
        'user_id',
        'guest_session_id',
        'lines',
        'subtotal',
        'shipping',
        'discount',
        'tax',
        'total',
        'total_cents',
        'currency',
        'coupon_code',
        'cart_hash',
        'shipping_address',
        'consumed_at',
        'email',
        'locale',
        'marketing_consent',
        'consent_ip',
        'reminder_sent_at',
        'recovered_at',
    ];

    protected $casts = [
        'lines' => 'array',
        'shipping_address' => 'array',
        'subtotal' => 'decimal:2',
        'shipping' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'total_cents' => 'integer',
        'consumed_at' => 'datetime',
        'marketing_consent' => 'boolean',
        'reminder_sent_at' => 'datetime',
        'recovered_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    /**
     * Snapshot of a cart as it is charged: call after the cart prices were refreshed and the totals computed.
     *
     * @param  array{items: float, shipping: float, discount: float, total: float, vat: array{amount: float}}  $totals
     * @param  array<int, float>  $previousPrices  cart item id => price the line had before it was refreshed
     */
    public static function capture(
        Cart $cart,
        array $totals,
        ?int $userId,
        ?string $guestSessionId,
        ?string $couponCode,
        array $previousPrices = [],
        array $shippingAddress = [],
    ): self {
        $lines = $cart->cartItems->map(function (CartItem $item) use ($previousPrices) {
            $variant = $item->productVariant ?? $item->product?->defaultVariant;
            $line = [
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'size_id' => $item->size_id,
                'quantity' => (int) $item->quantity,
                'price' => round((float) $item->price, 2),
                'product_name' => (string) ($item->product?->name ?? 'Unknown Product'),
                'variant_name' => $variant?->name,
                'sku' => $variant && $item->product ? $item->product->base_sku.$variant->sku_suffix : $item->product?->sku,
                'price_changed' => array_key_exists($item->id, $previousPrices),
            ];

            if ($line['price_changed']) {
                $line['previous_price'] = round((float) $previousPrices[$item->id], 2);
            }

            return $line;
        })->values()->all();

        $couponCode = trim((string) $couponCode);

        return static::create([
            'user_id' => $userId,
            'guest_session_id' => $guestSessionId,
            'lines' => $lines,
            'subtotal' => $totals['items'],
            'shipping' => $totals['shipping'],
            'discount' => $totals['discount'],
            'tax' => $totals['vat']['amount'],
            'total' => $totals['total'],
            'total_cents' => (int) round($totals['total'] * 100),
            'currency' => strtoupper((string) config('cashier.currency', 'eur')),
            'coupon_code' => $couponCode !== '' ? $couponCode : null,
            'cart_hash' => $cart->contentHash(),
            'shipping_address' => $shippingAddress ?: null,
        ]);
    }

    public function prunable(): Builder
    {
        return static::query()
            ->where(function (Builder $query) {
                $query->whereNull('consumed_at')
                    ->where('created_at', '<', now()->subDays(self::PRUNE_UNCONSUMED_AFTER_DAYS));
            })
            ->orWhere(function (Builder $query) {
                $query->whereNotNull('consumed_at')
                    ->where('consumed_at', '<', now()->subDays(self::PRUNE_CONSUMED_AFTER_DAYS));
            });
    }
}
