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
        'size_id',
        'quantity',
        'price',
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
