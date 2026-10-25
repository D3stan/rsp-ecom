<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function getTotalAttribute(): float
    {
        return $this->quantity * $this->price;
    }

    public function getCurrentPriceAttribute(): float
    {
        $variant = $this->productVariant ?? $this->product?->defaultVariant;

        return (float) ($variant?->price ?? $this->product->price);
    }

    public function getHasPriceChangedAttribute(): bool
    {
        return abs((float) $this->price - $this->current_price) >= 0.005;
    }

    public function updatePrice(): bool
    {
        return $this->update(['price' => $this->current_price]);
    }
}
