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
