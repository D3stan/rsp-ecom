<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'compare_price',
        'stock_quantity',
        'sku',
        'images',
        'status',
        'featured',
        'category_id',
        'size_id',
    ];

    protected $casts = [
        'images' => 'array',
        'price' => 'decimal:2',
        'compare_price' => 'decimal:2',
        'featured' => 'boolean',
    ];

    // Relationships
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(Size::class);
    }

    // For compatibility with frontend, return single size as array
    public function sizes()
    {
        return $this->size ? [$this->size] : [];
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function approvedReviews(): HasMany
    {
        return $this->hasMany(Review::class)->where('is_approved', true);
    }

    // Image handling methods
    public function getImageUrlsAttribute(): array
    {
        if (empty($this->images)) {
            return ['/images/product.png'];
        }

        return collect($this->images)->map(function ($image) {
            // If it's already a full URL (from cloud storage), return as is
            if (filter_var($image, FILTER_VALIDATE_URL)) {
                return $image;
            }
            
            // Otherwise, generate URL from storage
            return Storage::url("products/{$this->id}/{$image}");
        })->toArray();
    }

    public function getMainImageUrlAttribute(): ?string
    {
        if (empty($this->images)) {
            return '/images/product.png';
        }
        
        $firstImage = $this->images[0];
        
        // If it's already a full URL, return as is
        if (filter_var($firstImage, FILTER_VALIDATE_URL)) {
            return $firstImage;
        }
        
        return Storage::url("products/{$this->id}/{$firstImage}");
    }

    public function getImageUrl(string $filename): string
    {
        // If it's already a full URL, return as is
        if (filter_var($filename, FILTER_VALIDATE_URL)) {
            return $filename;
        }
        
        return Storage::url("products/{$this->id}/{$filename}");
    }

    public function addImage(string $filename): void
    {
        $images = $this->images ?? [];
        $images[] = $filename;
        $this->update(['images' => $images]);
    }

    public function removeImage(string $filename): void
    {
        $images = collect($this->images ?? [])->filter(fn($image) => $image !== $filename)->values()->toArray();
        $this->update(['images' => $images]);
        
        // Delete the actual file
        Storage::delete("products/{$this->id}/{$filename}");
    }

    public function clearImages(): void
    {
        if (!empty($this->images)) {
            foreach ($this->images as $image) {
                // Handle both local files and URLs
                if (filter_var($image, FILTER_VALIDATE_URL)) {
                    // For cloud storage, you might need a different deletion method
                    continue;
                } else {
                    Storage::delete("products/{$this->id}/{$image}");
                }
            }
        }
        $this->update(['images' => []]);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeFeatured($query)
    {
        return $query->where('featured', true);
    }

    public function scopeInStock($query)
    {
        return $query->where('stock_quantity', '>', 0);
    }

    // Additional helper methods
    public function getAverageRatingAttribute(): float
    {
        return $this->approvedReviews()->avg('rating') ?? 0;
    }

    public function getReviewCountAttribute(): int
    {
        return $this->approvedReviews()->count();
    }

    public function getDiscountPercentageAttribute(): ?float
    {
        if (!$this->compare_price || $this->compare_price <= $this->price) {
            return null;
        }

        return round((($this->compare_price - $this->price) / $this->compare_price) * 100, 2);
    }

    public function isInStock(): bool
    {
        return $this->stock_quantity > 0;
    }

    public function canAddToCart(int $quantity = 1): bool
    {
        return $this->status === 'active' && $this->stock_quantity >= $quantity;
    }

    public function decrementStock(int $quantity): bool
    {
        if ($this->stock_quantity < $quantity) {
            return false;
        }

        return $this->decrement('stock_quantity', $quantity);
    }

    public function incrementStock(int $quantity): bool
    {
        return $this->increment('stock_quantity', $quantity);
    }
}
