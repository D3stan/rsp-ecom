<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Size;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class GuestCartService
{
    private const GUEST_CART_KEY = 'guest_cart';
    private const GUEST_SESSION_KEY = 'guest_session_id';

    /**
     * Get or create a guest session ID
     */
    public function getGuestSessionId(): string
    {
        if (!Session::has(self::GUEST_SESSION_KEY)) {
            Session::put(self::GUEST_SESSION_KEY, Str::uuid()->toString());
        }

        return Session::get(self::GUEST_SESSION_KEY);
    }

    /**
     * Get guest cart items
     */
    public function getCartItems(): array
    {
        $cart = Session::get(self::GUEST_CART_KEY, []);
        $cartItems = [];

        foreach ($cart as $item) {
            $product = Product::find($item['product_id']);
            $size = $item['size_id'] ? Size::find($item['size_id']) : null;
            
            if ($product) {
                $cartItems[] = [
                    'id' => $item['id'],
                    'product' => $product,
                    'size' => $size,
                    'quantity' => $item['quantity'],
                    'price' => $size ? $size->price : $product->price,
                    'total' => ($size ? $size->price : $product->price) * $item['quantity'],
                ];
            }
        }

        return $cartItems;
    }

    /**
     * Add item to guest cart
     */
    public function addItem(int $productId, ?int $sizeId = null, int $quantity = 1): void
    {
        $cart = Session::get(self::GUEST_CART_KEY, []);
        $itemKey = $productId . '_' . ($sizeId ?? 'default');

        // Check if item already exists
        $existingKey = null;
        foreach ($cart as $key => $item) {
            if ($item['product_id'] == $productId && $item['size_id'] == $sizeId) {
                $existingKey = $key;
                break;
            }
        }

        if ($existingKey !== null) {
            // Update quantity
            $cart[$existingKey]['quantity'] += $quantity;
        } else {
            // Add new item
            $cart[] = [
                'id' => Str::uuid()->toString(),
                'product_id' => $productId,
                'size_id' => $sizeId,
                'quantity' => $quantity,
                'added_at' => now(),
            ];
        }

        Session::put(self::GUEST_CART_KEY, $cart);
    }

    /**
     * Update item quantity in guest cart
     */
    public function updateItem(string $itemId, int $quantity): bool
    {
        $cart = Session::get(self::GUEST_CART_KEY, []);
        
        foreach ($cart as $key => $item) {
            if ($item['id'] === $itemId) {
                if ($quantity <= 0) {
                    unset($cart[$key]);
                } else {
                    $cart[$key]['quantity'] = $quantity;
                }
                Session::put(self::GUEST_CART_KEY, array_values($cart));
                return true;
            }
        }

        return false;
    }

    /**
     * Remove item from guest cart
     */
    public function removeItem(string $itemId): bool
    {
        $cart = Session::get(self::GUEST_CART_KEY, []);
        
        foreach ($cart as $key => $item) {
            if ($item['id'] === $itemId) {
                unset($cart[$key]);
                Session::put(self::GUEST_CART_KEY, array_values($cart));
                return true;
            }
        }

        return false;
    }

    /**
     * Clear guest cart
     */
    public function clearCart(): void
    {
        Session::forget(self::GUEST_CART_KEY);
    }

    /**
     * Get cart count
     */
    public function getCartCount(): int
    {
        $cart = Session::get(self::GUEST_CART_KEY, []);
        return array_sum(array_column($cart, 'quantity'));
    }

    /**
     * Check if cart is empty
     */
    public function isEmpty(): bool
    {
        $cart = Session::get(self::GUEST_CART_KEY, []);
        return empty($cart);
    }

    /**
     * Calculate cart totals
     */
    public function calculateTotals(): array
    {
        $items = $this->getCartItems();
        $subtotal = 0;

        foreach ($items as $item) {
            $subtotal += $item['total'];
        }

        // Basic tax calculation (10%)
        $tax = $subtotal * 0.10;
        
        // Basic shipping calculation
        $shipping = $subtotal >= 100 ? 0 : 15.00;

        $total = $subtotal + $tax + $shipping;

        return [
            'subtotal' => $subtotal,
            'tax' => $tax,
            'shipping' => $shipping,
            'total' => $total,
            'items' => $items,
        ];
    }

    /**
     * Transfer guest cart to user account
     */
    public function transferToUser(int $userId): void
    {
        $guestItems = $this->getCartItems();
        
        foreach ($guestItems as $item) {
            // Check if user already has this item in cart
            $existingCartItem = \App\Models\Cart::where('user_id', $userId)
                ->where('product_id', $item['product']['id'])
                ->where('size_id', $item['size']['id'] ?? null)
                ->first();

            if ($existingCartItem) {
                // Update quantity
                $existingCartItem->quantity += $item['quantity'];
                $existingCartItem->save();
            } else {
                // Create new cart item
                \App\Models\Cart::create([
                    'user_id' => $userId,
                    'product_id' => $item['product']['id'],
                    'size_id' => $item['size']['id'] ?? null,
                    'quantity' => $item['quantity'],
                ]);
            }
        }

        // Clear guest cart after transfer
        $this->clearCart();
    }
}
