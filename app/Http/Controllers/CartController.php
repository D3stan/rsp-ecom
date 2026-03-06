<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Size;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Inertia\Response;
use Artesaos\SEOTools\Facades\SEOMeta;

class CartController extends Controller
{
    /**
     * Display the cart page
     */
    public function index()
    {
        // Set noindex for cart page
        SEOMeta::addMeta('robots', 'noindex,nofollow', 'name');
        SEOMeta::setTitle('Shopping Cart – ' . config('app.name'));
        
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

        $cartItems = $cart->cartItems()->with(['product.category', 'product.size', 'size', 'productVariant'])->get();

        // Fix any cart items that have shipping cost incorrectly added to price
        // Also fix cart items missing size_id when product has a size
        foreach ($cartItems as $item) {
            $updated = false;

            if ($item->price != $item->product->price) {
                $item->price = $item->product->price;
                $updated = true;
            }

            // If cart item doesn't have a size but product does, assign it
            if (!$item->size_id && $item->product->size_id) {
                $item->size_id = $item->product->size_id;
                $updated = true;
            }

            if ($updated) {
                $item->save();
            }
        }

        // Reload to get updated size relationship
        $cart->load(['cartItems.size', 'cartItems.productVariant']);

        return Inertia::render('cart', [
            'cart' => $cart,
            'cartItems' => $cartItems->map(function ($item) {
                $variant = $item->productVariant;
                $product = $item->product;

                return [
                    'id' => $item->id,
                    'product' => [
                        'id' => $product->id,
                        'name' => $product->name,
                        'slug' => $product->slug,
                        'image' => $variant?->main_image_url ?? $product->image_url,
                        'category' => $product->category?->name,
                    ],
                    'variant' => $variant ? [
                        'id' => $variant->id,
                        'name' => $variant->name,
                        'price' => (float) $variant->price,
                        'image' => $variant->main_image_url,
                        'max_quantity' => $variant->stock_quantity,
                    ] : null,
                    'quantity' => $item->quantity,
                    'price' => (float) $item->price,
                    'total' => (float) $item->total,
                    'size' => $item->size?->name,
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
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'product_variant_id' => 'required|exists:product_variants,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $variant = ProductVariant::findOrFail($validated['product_variant_id']);

        // Verify variant belongs to product
        if ($variant->product_id != $validated['product_id']) {
            return response()->json(['error' => 'Invalid variant for product'], 422);
        }

        // Check stock
        if (!$variant->canAddToCart($validated['quantity'])) {
            return response()->json([
                'error' => 'Not enough stock',
                'available' => $variant->stock_quantity,
            ], 422);
        }

        // Get or create cart
        $cart = $this->getOrCreateCart();

        // Check if item already in cart
        $cartItem = $cart->cartItems()
            ->where('product_id', $validated['product_id'])
            ->where('product_variant_id', $validated['product_variant_id'])
            ->first();

        if ($cartItem) {
            // Update quantity
            $newQuantity = $cartItem->quantity + $validated['quantity'];
            if (!$variant->canAddToCart($newQuantity)) {
                return response()->json([
                    'error' => 'Not enough stock for total quantity',
                    'available' => $variant->stock_quantity,
                ], 422);
            }
            $cartItem->update(['quantity' => $newQuantity]);
        } else {
            // Create new cart item
            $cart->cartItems()->create([
                'product_id' => $validated['product_id'],
                'product_variant_id' => $validated['product_variant_id'],
                'quantity' => $validated['quantity'],
                'price' => $variant->price,
                'size_id' => $variant->product->size_id,
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
