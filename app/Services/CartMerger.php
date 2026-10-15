<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Moves a guest cart (keyed by the session id) into the cart of the user who just signed in, so that signing in,
 * registering or using Google never empties the cart. The applied coupon lives in the session, which survives the
 * session-id rotation at login, so it needs no handling here.
 */
class CartMerger
{
    public function merge(string $guestSessionId, User $user): void
    {
        DB::transaction(function () use ($guestSessionId, $user) {
            $guestCart = Cart::where('session_id', $guestSessionId)
                ->whereNull('user_id')
                ->with(['cartItems.product', 'cartItems.productVariant'])
                ->lockForUpdate()
                ->first();

            if (! $guestCart) {
                return;
            }

            if ($guestCart->cartItems->isNotEmpty()) {
                // The same cart CartController uses for a signed-in customer
                $userCart = Cart::firstOrCreate(['user_id' => $user->id]);
                $userItems = $userCart->cartItems()->get();

                foreach ($guestCart->cartItems as $guestItem) {
                    $this->mergeLine($userCart, $userItems, $guestItem);
                }
            }

            $guestCart->cartItems()->delete();
            $guestCart->delete();
        });
    }

    /**
     * @param  \Illuminate\Support\Collection<int, CartItem>  $userItems
     */
    private function mergeLine(Cart $userCart, $userItems, CartItem $guestItem): void
    {
        $cap = $this->maxQuantity($guestItem);

        $same = $userItems->first(fn (CartItem $item) => (int) $item->product_id === (int) $guestItem->product_id
            && (int) $item->product_variant_id === (int) $guestItem->product_variant_id);

        if ($same) {
            // Summed and capped, but never below what the customer already had in their cart
            $same->update(['quantity' => max($same->quantity, min($same->quantity + $guestItem->quantity, $cap))]);

            return;
        }

        $quantity = min($guestItem->quantity, $cap);
        if ($quantity < 1) {
            return;
        }

        // cart_items is unique on (cart_id, product_id): one line per product. Another variant of the same product
        // in the user's cart is replaced by the guest's line, the customer's latest choice.
        $userItems->where('product_id', $guestItem->product_id)->each(fn (CartItem $item) => $item->delete());

        $userCart->cartItems()->create([
            'product_id' => $guestItem->product_id,
            'product_variant_id' => $guestItem->product_variant_id,
            'quantity' => $quantity,
            'price' => $guestItem->price,
            'size_id' => $guestItem->size_id,
        ]);
    }

    /**
     * The line limit: the stock of its variant (the product's for a line without variant), and
     * orders.max_qty_per_line when it is set (0 = unlimited), as CartController enforces when adding.
     */
    private function maxQuantity(CartItem $item): int
    {
        $cap = (int) ($item->productVariant?->stock_quantity ?? $item->product?->stock_quantity ?? 0);

        $max = (int) settings('orders.max_qty_per_line', 0);
        if ($max > 0) {
            $cap = min($cap, $max);
        }

        return $cap;
    }
}
