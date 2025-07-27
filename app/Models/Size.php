<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Size extends Model
{
    protected $fillable = [
        'name',
        'length',
        'width',
        'height',
        'box_type',
        'shipping_cost',
    ];

    protected $casts = [
        'length' => 'decimal:2',
        'width' => 'decimal:2',
        'height' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
    ];

    // Relationships
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    // Calculate volume for shipping purposes
    public function getVolumeAttribute(): float
    {
        return $this->length * $this->width * $this->height;
    }
}
