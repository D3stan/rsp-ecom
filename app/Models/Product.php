<?php

namespace App\Models;

use App\Traits\RegeneratesSitemap;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use HasFactory, RegeneratesSitemap;
    protected $fillable = [
        'name',
        'slug',
        'description',
        'seo_title',
        'seo_description',
        'social_image_url',
        'price',
        'compare_price',
        'stock_quantity',
        'sku',
        'base_sku',
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

    protected $appends = ['image_url'];

    public function getImageUrlAttribute(): string
    {
        if ($this->images && count($this->images) > 0) {
            return asset('storage/products/' . $this->id . '/' . $this->images[0]);
        }

        // Return a default placeholder image if no images are set
        return asset('images/product.png');
    }

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

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function activeVariants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)
            ->where('status', 'active')
            ->orderBy('sort_order');
    }

    public function defaultVariant(): HasOne
    {
        return $this->hasOne(ProductVariant::class)
            ->where('is_default', true);
    }

    // Image handling methods
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
        try {
            $images = $this->images ?? [];
            $images[] = $filename;
            $this->update(['images' => $images]);
            \Log::info('Image added to product', ['product_id' => $this->id, 'filename' => $filename, 'total_images' => count($images)]);
        } catch (\Exception $e) {
            \Log::error('Failed to add image to product', [
                'product_id' => $this->id,
                'filename' => $filename,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
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

    /**
     * Get fields that should trigger sitemap regeneration when changed
     */
    protected function getSitemapImportantFields(): array
    {
        return ['slug', 'status', 'name', 'stock_quantity'];
    }

    /**
     * Backward compatibility accessors - delegate to default variant
     */

    public function getPriceAttribute(): ?float
    {
        return $this->defaultVariant?->price;
    }

    public function getStockQuantityAttribute(): ?int
    {
        return $this->defaultVariant?->stock_quantity;
    }

    public function getImagesAttribute(): ?array
    {
        return $this->defaultVariant?->images;
    }

    public function getDescriptionAttribute(): ?string
    {
        return $this->defaultVariant?->description;
    }

    public function getSkuAttribute(): ?string
    {
        return $this->defaultVariant?->full_sku;
    }

    public function getMainImageUrlAttribute(): string
    {
        return $this->defaultVariant?->main_image_url ?? '/images/product.png';
    }

    public function getImageUrlsAttribute(): array
    {
        return $this->defaultVariant?->image_urls ?? ['/images/product.png'];
    }

    public function getComparePriceAttribute(): ?float
    {
        return $this->defaultVariant?->compare_price;
    }

    public function getDiscountPercentageAttribute(): ?float
    {
        $defaultVariant = $this->defaultVariant;
        if (!$defaultVariant) {
            return null;
        }

        $comparePrice = $defaultVariant->compare_price ?? 0;
        $price = $defaultVariant->price ?? 0;

        if (!$comparePrice || $comparePrice <= $price) {
            return null;
        }

        return round((($comparePrice - $price) / $comparePrice) * 100, 2);
    }
}
