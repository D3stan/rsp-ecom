<?php

namespace App\Observers;

use App\Models\ProductVariant;
use App\Services\StockService;

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
        return app(StockService::class);
    }
}
