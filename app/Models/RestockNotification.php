<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestockNotification extends Model
{
    public const SOURCE_CART = 'cart';

    public const SOURCE_WISHLIST = 'wishlist';

    public const COOLDOWN_DAYS = 7;

    protected $fillable = [
        'user_id',
        'product_variant_id',
        'source',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
