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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(ReviewMedia::class)->orderBy('sort_order')->orderBy('id');
    }

    public function approvedMedia(): HasMany
    {
        return $this->media()->where('status', ReviewMedia::STATUS_APPROVED);
    }

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

    public function getStarsAttribute(): string
    {
        return str_repeat('★', $this->rating).str_repeat('☆', 5 - $this->rating);
    }

    public function approve(): bool
    {
        return $this->update(['is_approved' => true]);
    }

    public function reject(): bool
    {
        return DB::transaction(function () {
            static::rejectMediaOf([$this->id]);

            return $this->update(['is_approved' => false]);
        });
    }

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

    public static function boot()
    {
        parent::boot();

        static::saving(function ($review) {
            if ($review->rating < 1 || $review->rating > 5) {
                throw new \InvalidArgumentException('Rating must be between 1 and 5');
            }
        });

        static::deleted(function (Review $review) {
            $directory = "reviews/{$review->id}";
            DB::afterCommit(fn () => Storage::disk(ReviewMedia::DISK)->deleteDirectory($directory));
        });
    }
}
