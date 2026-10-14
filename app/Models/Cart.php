<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'session_id',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    // Cart calculations
    public function getSubtotalAttribute(): float
    {
        return $this->cartItems->sum(function ($item) {
            return $item->quantity * $item->price;
        });
    }

    public function getTotalItemsAttribute(): int
    {
        return $this->cartItems->sum('quantity');
    }

    public function getShippingCostAttribute(): float
    {
        // Calculate shipping based on cart items' sizes
        return $this->cartItems->sum(function ($item) {
            // Use the size from the cart item, not from the product
            return $item->size ? $item->size->shipping_cost : 0;
        });
    }

    public function getTotalAttribute(): float
    {
        return $this->subtotal + $this->shipping_cost;
    }

    /**
     * Fingerprint of what is being bought (variant, size, quantity, price), independent of item order.
     * Stored in the Stripe session metadata when checkout starts and compared when the order is created.
     */
    public function contentHash(): string
    {
        $lines = $this->cartItems->map(fn ($item) => implode('|', [
            $item->product_variant_id ?? 'p'.$item->product_id,
            $item->size_id ?? '-',
            (int) $item->quantity,
            number_format((float) $item->price, 2, '.', ''),
        ]))->sort()->values()->all();

        return sha1(implode("\n", $lines));
    }

    /**
     * Bring every line to the current catalogue price of its own variant (CartItem::current_price), so a change of
     * the shop price between "added to cart" and "pay" is charged at the new price of the RIGHT variant, never at
     * the default variant's price. Returns the lines that changed: cart item id => previous price.
     *
     * @return array<int, float>
     */
    public function refreshPrices(): array
    {
        $this->loadMissing(['cartItems.product.defaultVariant', 'cartItems.productVariant']);

        $changed = [];

        foreach ($this->cartItems as $item) {
            if ($item->has_price_changed) {
                $changed[$item->id] = (float) $item->price;
                $item->updatePrice();
            }
        }

        return $changed;
    }

    // Helper methods
    public function addItem(Product $product, int $quantity = 1): CartItem
    {
        $existingItem = $this->cartItems()->where('product_id', $product->id)->first();

        if ($existingItem) {
            $existingItem->increment('quantity', $quantity);
            return $existingItem;
        }

        return $this->cartItems()->create([
            'product_id' => $product->id,
            'quantity' => $quantity,
            'price' => $product->price,
        ]);
    }

    public function removeItem(Product $product): bool
    {
        return $this->cartItems()->where('product_id', $product->id)->delete() > 0;
    }

    public function updateItemQuantity(Product $product, int $quantity): bool
    {
        if ($quantity <= 0) {
            return $this->removeItem($product);
        }

        return $this->cartItems()
            ->where('product_id', $product->id)
            ->update(['quantity' => $quantity]) > 0;
    }

    public function clear(): bool
    {
        return $this->cartItems()->delete() > 0;
    }

    // Static methods for guest carts
    public static function findBySession(string $sessionId): ?self
    {
        return static::where('session_id', $sessionId)->first();
    }

    public static function createForSession(string $sessionId): self
    {
        return static::create(['session_id' => $sessionId]);
    }
}
