<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;
    protected $fillable = [
        'order_id',
        'product_id', // Can be null for guest orders where we don't have a proper product reference
        'product_name',
        'quantity',
        'price',
        'total',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'total' => 'decimal:2',
        'quantity' => 'integer',
    ];

    // Relationships
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withDefault([
            'id' => $this->product_id,
            'name' => $this->product_name ?? 'Unknown Product',
            'price' => $this->price,
            'image_url' => null,
        ]);
    }

    // Accessors
    public function getCalculatedTotalAttribute(): float
    {
        return $this->quantity * $this->price;
    }

    // Check if the stored total matches calculated total
    public function getIsTotalCorrectAttribute(): bool
    {
        return abs($this->total - $this->calculated_total) < 0.01;
    }
}
