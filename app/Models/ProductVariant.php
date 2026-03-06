<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'name',
        'sku_suffix',
        'description',
        'price',
        'stock_quantity',
        'images',
        'is_default',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'images' => 'array',
        'price' => 'decimal:2',
        'is_default' => 'boolean',
        'sort_order' => 'integer',
        'stock_quantity' => 'integer',
        'status' => 'string',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getFullSkuAttribute(): string
    {
        return $this->product->base_sku . $this->sku_suffix;
    }

    public function getImageUrlsAttribute(): array
    {
        if (empty($this->images)) {
            return ['/images/product.png'];
        }

        return collect($this->images)->map(function ($image) {
            if (filter_var($image, FILTER_VALIDATE_URL)) {
                return $image;
            }
            return Storage::url("products/{$this->product_id}/variants/{$this->id}/{$image}");
        })->toArray();
    }

    public function getMainImageUrlAttribute(): string
    {
        $urls = $this->image_urls;
        return $urls[0] ?? '/images/product.png';
    }

    public function addImage(string $filename): void
    {
        $images = $this->images ?? [];
        $images[] = $filename;
        $this->update(['images' => $images]);
    }

    public function removeImage(string $filename): void
    {
        // Sanitize filename to prevent path traversal
        $filename = basename($filename);

        $images = collect($this->images ?? [])
            ->filter(fn($img) => $img !== $filename)
            ->values()
            ->toArray();

        $this->update(['images' => $images]);

        $deleted = Storage::delete("products/{$this->product_id}/variants/{$this->id}/{$filename}");

        if (!$deleted) {
            \Log::warning('Failed to delete variant image', [
                'variant_id' => $this->id,
                'filename' => $filename,
            ]);
        }
    }

    public function clearImages(): void
    {
        if (!empty($this->images)) {
            foreach ($this->images as $image) {
                if (!filter_var($image, FILTER_VALIDATE_URL)) {
                    $filename = basename($image); // Sanitize
                    $deleted = Storage::delete("products/{$this->product_id}/variants/{$this->id}/{$filename}");

                    if (!$deleted) {
                        \Log::warning('Failed to delete variant image during clear', [
                            'variant_id' => $this->id,
                            'filename' => $filename,
                        ]);
                    }
                }
            }
        }
        $this->update(['images' => []]);
    }

    public function isInStock(): bool
    {
        return $this->stock_quantity > 0 && $this->status === 'active';
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
