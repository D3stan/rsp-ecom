<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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

    /** Customer photos, in upload order (all statuses: moderation only). */
    public function media(): HasMany
    {
        return $this->hasMany(ReviewMedia::class)->orderBy('sort_order')->orderBy('id');
    }

    /** The photos the storefront may show. */
    public function approvedMedia(): HasMany
    {
        return $this->media()->where('status', ReviewMedia::STATUS_APPROVED);
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
        return str_repeat('★', $this->rating).str_repeat('☆', 5 - $this->rating);
    }

    // Helper methods
    public function approve(): bool
    {
        return $this->update(['is_approved' => true]);
    }

    /** Rejecting a review rejects its photos too (and takes them off the home page). */
    public function reject(): bool
    {
        return DB::transaction(function () {
            static::rejectMediaOf([$this->id]);

            return $this->update(['is_approved' => false]);
        });
    }

    /** Approve the text and every photo still waiting; photos an admin rejected stay rejected. */
    public function approveWithMedia(): bool
    {
        return DB::transaction(function () {
            ReviewMedia::where('review_id', $this->id)
                ->where('status', ReviewMedia::STATUS_PENDING)
                ->update(['status' => ReviewMedia::STATUS_APPROVED]);

            return $this->approve();
        });
    }

    /** @param  array<int>  $reviewIds */
    public static function rejectMediaOf(array $reviewIds): void
    {
        ReviewMedia::whereIn('review_id', $reviewIds)->update(['status' => ReviewMedia::STATUS_REJECTED, 'featured' => false]);
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

        // The DB cascade removes the review_media rows; the files go once the delete is committed. A user or
        // product delete reaches here too: their deleting hooks delete the reviews one by one.
        static::deleted(function (Review $review) {
            $directory = "reviews/{$review->id}";
            DB::afterCommit(fn () => Storage::disk(ReviewMedia::DISK)->deleteDirectory($directory));
        });
    }
}
