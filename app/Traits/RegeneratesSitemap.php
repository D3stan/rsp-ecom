<?php

namespace App\Traits;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

trait RegeneratesSitemap
{
    /**
     * Boot the trait
     */
    protected static function bootRegeneratesSitemap(): void
    {
        // Listen for model events that should trigger sitemap regeneration
        static::created(function ($model) {
            $model->scheduleRegeneration();
        });

        static::updated(function ($model) {
            // Only regenerate if important fields changed
            if ($model->shouldRegenerateSitemap()) {
                $model->scheduleRegeneration();
            }
        });

        static::deleted(function ($model) {
            $model->scheduleRegeneration();
        });
    }

    /**
     * Schedule sitemap regeneration (debounced to avoid too frequent regenerations)
     */
    public function scheduleRegeneration(): void
    {
        $cacheKey = 'sitemap_regeneration_scheduled';
        
        // Only schedule if not already scheduled in the last 5 minutes
        if (!Cache::has($cacheKey)) {
            Cache::put($cacheKey, true, now()->addMinutes(5));
            
            // Schedule the regeneration to run in the background
            dispatch(function () {
                try {
                    Artisan::call('generate:sitemap-index');
                } catch (\Exception $e) {
                    // Log error but don't fail the main operation
                    logger()->error('Failed to regenerate sitemap: ' . $e->getMessage());
                }
            })->afterResponse();
        }
    }

    /**
     * Determine if sitemap should be regenerated based on changed attributes
     */
    public function shouldRegenerateSitemap(): bool
    {
        $importantFields = $this->getSitemapImportantFields();
        
        foreach ($importantFields as $field) {
            if ($this->wasChanged($field)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get fields that should trigger sitemap regeneration when changed
     */
    protected function getSitemapImportantFields(): array
    {
        // Default fields - can be overridden in models
        return ['slug', 'status', 'is_active', 'name'];
    }
}
