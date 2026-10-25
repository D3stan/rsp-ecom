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
        'product_id',
        'product_variant_id',
        'product_name',
        'size_id',
        'quantity',
        'price',
        'total',
        'oversold_qty',
        'restocked_qty',
    ];

    protected $hidden = ['oversold_qty', 'restocked_qty'];

    protected $casts = [
        'oversold_qty' => 'integer',
        'restocked_qty' => 'integer',
        'price' => 'decimal:2',
        'total' => 'decimal:2',
        'quantity' => 'integer',
    ];

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

    public function size(): BelongsTo
    {
        return $this->belongsTo(Size::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function getCalculatedTotalAttribute(): float
    {
        return $this->quantity * $this->price;
    }

    public function getIsTotalCorrectAttribute(): bool
    {
        return abs($this->total - $this->calculated_total) < 0.01;
    }
}
