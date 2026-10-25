<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ReviewMedia extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const DISK = 'public';

    protected $table = 'review_media';

    protected $fillable = [
        'review_id',
        'path',
        'thumbnail_path',
        'width',
        'height',
        'sort_order',
        'status',
        'featured',
    ];

    protected $casts = [
        'featured' => 'boolean',
        'width' => 'integer',
        'height' => 'integer',
        'sort_order' => 'integer',
    ];

    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function approve(): void
    {
        $this->update(['status' => self::STATUS_APPROVED]);
    }

    public function reject(): void
    {
        $this->update(['status' => self::STATUS_REJECTED, 'featured' => false]);
    }

    public function url(): string
    {
        return Storage::disk(self::DISK)->url($this->path);
    }

    public function thumbnailUrl(): string
    {
        return Storage::disk(self::DISK)->url($this->thumbnail_path);
    }

    /**
     * The shape the storefront receives (product page). Callers pass approved media only.
     *
     * @return array{id: int, url: string, thumbnail_url: string, width: int, height: int}
     */
    public function toStorefrontArray(): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url(),
            'thumbnail_url' => $this->thumbnailUrl(),
            'width' => $this->width,
            'height' => $this->height,
        ];
    }

    /**
     * The shape the admin moderation pages receive (every state).
     *
     * @return array{id: int, url: string, thumbnail_url: string, width: int, height: int, status: string, featured: bool}
     */
    public function toAdminArray(): array
    {
        return $this->toStorefrontArray() + [
            'status' => $this->status,
            'featured' => (bool) $this->featured,
        ];
    }
}
