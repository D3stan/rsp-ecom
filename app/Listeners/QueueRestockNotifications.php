<?php

namespace App\Listeners;

use App\Events\VariantRestocked;
use App\Jobs\SendRestockEmail;
use App\Models\CartItem;
use App\Models\ProductVariant;
use App\Models\RestockNotification;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Turns a restock into back-in-stock emails, on the queue (never inside the request that restocked).
 *
 * Recipients: registered users whose cart holds the variant, and (only when the event allows it: nothing else of the
 * product was buyable) users whose wishlist holds its product. Only opted-in, active, verified accounts; at most one
 * email per user and variant within RestockNotification::COOLDOWN_DAYS; one email per user per restock even when the
 * item is in both lists (the cart wins: it links to the cart).
 *
 * A restock_notifications row is written per recipient before SendRestockEmail is queued; the rows are the cooldown.
 */
class QueueRestockNotifications implements ShouldQueue
{
    public int $tries = 3;

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(VariantRestocked $event): void
    {
        if (! settings('email.enabled', true) || ! settings('email.back_in_stock', true)) {
            return;
        }

        $variant = ProductVariant::with('product')->find($event->variantId);

        // Sold out again (or switched off) before we got here: nothing to announce
        if (! $variant || ! $variant->isInStock() || $variant->product?->status !== 'active') {
            return;
        }

        // One worker at a time per variant, so two restock events in quick succession cannot both pass the cooldown
        Cache::lock('restock-notifications:'.$variant->id, 120)->block(30, function () use ($variant, $event) {
            $recipients = $this->recipients($variant, $event->notifyWishlists);

            foreach ($recipients as $userId => $source) {
                $notification = RestockNotification::create([
                    'user_id' => $userId,
                    'product_variant_id' => $variant->id,
                    'source' => $source,
                ]);

                SendRestockEmail::dispatch($notification->id);
            }

            if ($recipients !== []) {
                Log::info('Back-in-stock emails queued', ['variant_id' => $variant->id, 'count' => count($recipients)]);
            }
        });
    }

    /**
     * @return array<int, string> user id => source (cart before wishlist)
     */
    private function recipients(ProductVariant $variant, bool $notifyWishlists): array
    {
        $recipients = [];

        $cartUsers = $this->eligibleUsers($variant)
            ->whereIn('id', CartItem::query()
                ->join('carts', 'carts.id', '=', 'cart_items.cart_id')
                ->where('cart_items.product_variant_id', $variant->id)
                ->whereNotNull('carts.user_id')
                ->select('carts.user_id'))
            ->pluck('id');

        foreach ($cartUsers as $userId) {
            $recipients[(int) $userId] = RestockNotification::SOURCE_CART;
        }

        if ($notifyWishlists) {
            $wishlistUsers = $this->eligibleUsers($variant)
                ->whereIn('id', Wishlist::query()->where('product_id', $variant->product_id)->select('user_id'))
                ->pluck('id');

            foreach ($wishlistUsers as $userId) {
                $recipients[(int) $userId] ??= RestockNotification::SOURCE_WISHLIST;
            }
        }

        return $recipients;
    }

    /**
     * Opted-in, active, verified users who were not told about this variant within the cooldown.
     *
     * @return Builder<User>
     */
    private function eligibleUsers(ProductVariant $variant): Builder
    {
        return User::query()
            ->where('notify_back_in_stock', true)
            ->where('is_active', true)
            ->whereNotNull('email_verified_at')
            ->whereNotNull('email')
            ->whereNotExists(fn ($query) => $query->selectRaw('1')
                ->from('restock_notifications')
                ->whereColumn('restock_notifications.user_id', 'users.id')
                ->where('restock_notifications.product_variant_id', $variant->id)
                ->where('restock_notifications.created_at', '>=', now()->subDays(RestockNotification::COOLDOWN_DAYS)));
    }
}
