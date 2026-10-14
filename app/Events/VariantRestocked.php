<?php

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A variant became sellable again: active with stock > 0 after being out of stock or inactive.
 * Announced by StockService::announceIfRestocked() and dispatched only once the surrounding transaction commits
 * (a rolled-back restock tells nobody). Carries ids only; QueueRestockNotifications reads the current state.
 */
class VariantRestocked implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  bool  $notifyWishlists  false when another variant of the product could already be bought at the moment
     *                                 of the restock (wishlists are per product: those users could buy it already)
     */
    public function __construct(
        public int $variantId,
        public bool $notifyWishlists = true,
    ) {}
}
