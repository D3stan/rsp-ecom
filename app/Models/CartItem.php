<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\ProductVariant;

class CartItem extends Model
{
    use HasFactory;
    protected $fillable = [
        'cart_id',
        'product_id',
        'product_variant_id',
        'quantity',
        'price',
        'size_id',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'quantity' => 'integer',
    ];

    // Relationships
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(Size::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    // Accessors
    public function getTotalAttribute(): float
    {
        return $this->quantity * $this->price;
    }

    /**
     * Current catalogue price of the thing this line is for: its own variant's price (CartController::add copies it
     * into the line), else the product's default variant for legacy lines without a variant. Never the default
     * variant's price for a line that has another variant.
     */
    public function getCurrentPriceAttribute(): float
    {
        $variant = $this->productVariant ?? $this->product?->defaultVariant;

        return (float) ($variant?->price ?? $this->product->price);
    }

    // Check if the catalogue price of the line's variant has changed since adding to cart
    public function getHasPriceChangedAttribute(): bool
    {
        return abs((float) $this->price - $this->current_price) >= 0.005;
    }

    // Update price to the current catalogue price of the line's variant
    public function updatePrice(): bool
    {
        return $this->update(['price' => $this->current_price]);
    }
}
