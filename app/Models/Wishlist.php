<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Wishlist extends Model
{
    protected $fillable = [
        'user_id',
        'product_id',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // Helper methods
    public static function addToWishlist(User $user, Product $product): bool
    {
        return static::firstOrCreate([
            'user_id' => $user->id,
            'product_id' => $product->id,
        ])->wasRecentlyCreated;
    }

    public static function removeFromWishlist(User $user, Product $product): bool
    {
        return static::where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->delete() > 0;
    }

    public static function isInWishlist(User $user, Product $product): bool
    {
        return static::where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->exists();
    }
}
