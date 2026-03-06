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

    // Check if product price has changed since adding to cart
    public function getHasPriceChangedAttribute(): bool
    {
        return $this->price != $this->product->price;
    }

    public function getCurrentPriceAttribute(): float
    {
        return $this->product->price;
    }

    // Update price to current product price
    public function updatePrice(): bool
    {
        return $this->update(['price' => $this->product->price]);
    }
}
