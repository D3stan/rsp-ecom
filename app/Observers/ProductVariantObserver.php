<?php

namespace App\Observers;

use App\Models\ProductVariant;
use App\Services\StockService;

/**
 * Every Eloquent write to a variant (save, update, increment, create) goes past here, so stock that comes back through
 * any code path (admin form, quick stock, order cancellation, ...) is announced once by StockService.
 * Code that changes product_variants with a query-builder UPDATE bypasses model events and must call
 * StockService::announceIfRestocked() itself.
 */
class ProductVariantObserver
{
    public function created(ProductVariant $variant): void
    {
        $this->stock()->announceIfRestocked($variant->id, 0, (int) $variant->stock_quantity, false, $variant->status === 'active');
    }

    public function updated(ProductVariant $variant): void
    {
        if (! $variant->wasChanged(['stock_quantity', 'status'])) {
            return;
        }

        // During `updated` the original values are still the ones before this write
        $this->stock()->announceIfRestocked(
            $variant->id,
            (int) $variant->getOriginal('stock_quantity'),
            (int) $variant->stock_quantity,
            $variant->getOriginal('status') === 'active',
            $variant->status === 'active',
        );
    }

    private function stock(): StockService
    {
        // Resolved per event (not injected): the observer instance lives as long as the application.
        return app(StockService::class);
    }
}
