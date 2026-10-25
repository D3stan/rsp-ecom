<?php

namespace App\Models;

use App\Traits\RegeneratesSitemap;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory, RegeneratesSitemap;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'seo_title',
        'seo_description',
        'social_image_url',
        'image',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    protected function getSitemapImportantFields(): array
    {
        return ['slug', 'is_active', 'name'];
    }
}
