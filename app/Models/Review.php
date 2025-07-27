<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'product_id',
        'rating',
        'comment',
        'is_approved',
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_approved' => 'boolean',
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

    // Scopes
    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    public function scopePending($query)
    {
        return $query->where('is_approved', false);
    }

    public function scopeRating($query, int $rating)
    {
        return $query->where('rating', $rating);
    }

    // Accessors
    public function getStarsAttribute(): string
    {
        return str_repeat('★', $this->rating) . str_repeat('☆', 5 - $this->rating);
    }

    // Helper methods
    public function approve(): bool
    {
        return $this->update(['is_approved' => true]);
    }

    public function reject(): bool
    {
        return $this->update(['is_approved' => false]);
    }

    // Validation for rating (1-5 stars)
    public static function boot()
    {
        parent::boot();

        static::saving(function ($review) {
            if ($review->rating < 1 || $review->rating > 5) {
                throw new \InvalidArgumentException('Rating must be between 1 and 5');
            }
        });
    }
}
