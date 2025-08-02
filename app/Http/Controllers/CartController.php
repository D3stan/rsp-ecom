<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\Size;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;

class CartController extends Controller
{
    /**
     * Display the cart page
     */
    public function index()
    {
        $cart = $this->getOrCreateCart();
        
        if (!$cart) {
            return Inertia::render('cart', [
                'cart' => null,
                'cartItems' => [],
                'subtotal' => 0,
                'shippingCost' => 0,
                'total' => 0,
                'totalItems' => 0,
            ]);
        }

        $cartItems = $cart->cartItems()->with(['product.category', 'product.size'])->get();
        
        return Inertia::render('cart', [
            'cart' => $cart,
            'cartItems' => $cartItems->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product' => [
                        'id' => $item->product->id,
                        'name' => $item->product->name,
                        'slug' => $item->product->slug,
                        'image' => $item->product->images[0] ?? '/images/placeholder.jpg',
                        'category' => $item->product->category?->name,
                    ],
                    'quantity' => $item->quantity,
                    'price' => (float) $item->price,
                    'total' => (float) $item->total,
                    'size' => $item->product->size?->name,
                ];
            }),
            'subtotal' => (float) $cart->subtotal,
            'shippingCost' => (float) $cart->shipping_cost,
            'total' => (float) $cart->total,
            'totalItems' => $cart->total_items,
        ]);
    }

    /**
     * Add a product to cart
     */
    public function add(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'size_id' => 'nullable|exists:sizes,id',
        ]);

        $product = Product::findOrFail($request->product_id);
        
        // Check stock availability
        if (!$product->isInStock() || $product->stock_quantity < $request->quantity) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product is out of stock or insufficient quantity available.'
                ], 400);
            }
            
            return back()->withErrors([
                'message' => 'Product is out of stock or insufficient quantity available.'
            ]);
        }

        $cart = $this->getOrCreateCart();
        
        // Calculate price (base price + size adjustment if applicable)
        $price = $product->price;
        if ($request->size_id) {
            $size = Size::find($request->size_id);
            if ($size) {
                $price += $size->shipping_cost; // For now, use shipping_cost as price adjustment
            }
        }

        // Check if item already exists in cart
        $existingItem = $cart->cartItems()
            ->where('product_id', $request->product_id)
            ->first();

        if ($existingItem) {
            // Update quantity
            $newQuantity = $existingItem->quantity + $request->quantity;
            
            // Check if new quantity exceeds stock
            if ($newQuantity > $product->stock_quantity) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot add more items. Stock limit exceeded.'
                    ], 400);
                }
                
                return back()->withErrors([
                    'message' => 'Cannot add more items. Stock limit exceeded.'
                ]);
            }
            
            $existingItem->update([
                'quantity' => $newQuantity,
                'price' => $price, // Update price in case it changed
            ]);
        } else {
            // Create new cart item
            $cart->cartItems()->create([
                'product_id' => $request->product_id,
                'quantity' => $request->quantity,
                'price' => $price,
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Product added to cart successfully.',
                'cartCount' => $cart->fresh()->total_items,
            ]);
        }

        return back()->with('success', 'Product added to cart successfully.');
    }

    /**
     * Update cart item quantity
     */
    public function update(Request $request, CartItem $cartItem)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        // Verify cart item belongs to current user's cart
        $cart = $this->getOrCreateCart();
        if ($cartItem->cart_id !== $cart->id) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cart item not found.'
                ], 404);
            }
            
            return back()->withErrors(['message' => 'Cart item not found.']);
        }

        // Check stock availability
        if ($request->quantity > $cartItem->product->stock_quantity) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient stock available.',
                    'maxQuantity' => $cartItem->product->stock_quantity,
                ], 400);
            }
            
            return back()->withErrors([
                'message' => 'Insufficient stock available.',
                'maxQuantity' => $cartItem->product->stock_quantity,
            ]);
        }

        $cartItem->update([
            'quantity' => $request->quantity,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Cart updated successfully.',
                'cartCount' => $cart->fresh()->total_items,
            ]);
        }

        return back()->with('success', 'Cart updated successfully.');
    }

    /**
     * Remove item from cart
     */
    public function remove(CartItem $cartItem)
    {
        // Verify cart item belongs to current user's cart
        $cart = $this->getOrCreateCart();
        if ($cartItem->cart_id !== $cart->id) {
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cart item not found.'
                ], 404);
            }
            
            return back()->withErrors(['message' => 'Cart item not found.']);
        }

        $cartItem->delete();

        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Item removed from cart.',
                'cartCount' => $cart->fresh()->total_items,
            ]);
        }

        return back()->with('success', 'Item removed from cart.');
    }

    /**
     * Clear entire cart
     */
    public function clear()
    {
        $cart = $this->getOrCreateCart();
        $cart->cartItems()->delete();

        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Cart cleared successfully.',
                'cartCount' => 0,
            ]);
        }

        return back()->with('success', 'Cart cleared successfully.');
    }

    /**
     * Get cart count for header display
     */
    public function count()
    {
        $cart = $this->getOrCreateCart();
        
        return response()->json([
            'count' => $cart ? $cart->total_items : 0,
        ]);
    }

    /**
     * Apply coupon code
     */
    public function applyCoupon(Request $request)
    {
        $request->validate([
            'code' => 'required|string|max:50',
        ]);

        // TODO: Implement coupon functionality
        // For now, return a placeholder response
        
        return response()->json([
            'success' => false,
            'message' => 'Coupon functionality not yet implemented.',
        ]);
    }

    /**
     * Get or create cart for current user/session
     */
    private function getOrCreateCart(): ?Cart
    {
        if (Auth::check()) {
            // For authenticated users
            $cart = Cart::firstOrCreate([
                'user_id' => Auth::id(),
            ]);
        } else {
            // For guest users
            $sessionId = Session::getId();
            $cart = Cart::firstOrCreate([
                'session_id' => $sessionId,
            ]);
        }

        return $cart;
    }
}
