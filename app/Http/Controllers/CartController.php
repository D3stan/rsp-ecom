<?php

namespace App\Http\Controllers;

use App\Enums\OpsEventType;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Services\Ops\FunnelTracker;
use App\Support\CheckoutTotals;
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
        SEOMeta::setTitle('Shopping Cart – ' . settings('store.name'));
        
        $cart = $this->getOrCreateCart();
        
        if (!$cart) {
            return Inertia::render('cart', [
                'cart' => null,
                'cartItems' => [],
                'subtotal' => 0,
                'shippingCost' => 0,
                'total' => 0,
                'totalItems' => 0,
                'freeShippingRemaining' => null,
            ]);
        }

        $cartItems = $cart->cartItems()->with(['product.category', 'product.size', 'product.defaultVariant', 'size', 'productVariant'])->get();

        // Fix any cart items that have shipping cost incorrectly added to price
        // Also fix cart items missing size_id when product has a size
        foreach ($cartItems as $item) {
            $updated = false;

            // The line's own variant decides the price (the default variant's price is not the price of another variant)
            if ($item->has_price_changed) {
                $item->price = $item->current_price;
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

        // The same totals the checkout charges (coupon discount, free-shipping rule)
        $appliedCoupon = $this->getAppliedCoupon();
        $totals = CheckoutTotals::for($cart, $appliedCoupon);

        $subtotal = $totals['items'];
        $shippingCost = $totals['shipping'];
        $discountAmount = $totals['discount'];
        $total = $totals['total'];

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
            'subtotal' => $subtotal,
            'shippingCost' => $shippingCost,
            'discountAmount' => $discountAmount,
            'discountDescription' => $appliedCoupon['description'] ?? null,
            'couponCode' => $appliedCoupon['code'] ?? null,
            'total' => $total,
            'totalItems' => $cart->total_items,
            'freeShippingRemaining' => $totals['free_shipping_remaining'],
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

        // Check the per-line limit (orders.max_qty_per_line)
        if ($exceeded = $this->maxQuantityExceeded($validated['quantity'])) {
            return $exceeded;
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
            if ($exceeded = $this->maxQuantityExceeded($newQuantity)) {
                return $exceeded;
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

        app(FunnelTracker::class)->record(OpsEventType::AddToCart, $request);

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

        // Check stock availability (the variant's stock when the line has one, as in add())
        $availableStock = (int) ($cartItem->productVariant?->stock_quantity ?? $cartItem->product->stock_quantity);
        if ($request->quantity > $availableStock) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient stock available.',
                    'maxQuantity' => $availableStock,
                ], 400);
            }
            
            return back()->withErrors([
                'message' => 'Insufficient stock available.',
                'maxQuantity' => $availableStock,
            ]);
        }

        // Check the per-line limit (orders.max_qty_per_line)
        if ($exceeded = $this->maxQuantityExceeded((int) $request->quantity, $request->expectsJson())) {
            return $exceeded;
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

        // No flash: the new quantity and totals are visible on the cart page, a toast on every +/- is noise
        return back();
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

        $code = $request->input('code');

        // Use PromotionService to validate the coupon via Stripe
        $promotionService = app(\App\Services\PromotionService::class);
        $validation = $promotionService->validatePromotionCode($code);

        if (!$validation['valid']) {
            // Check if this is an Inertia request
            if ($request->header('X-Inertia')) {
                return back()->withErrors(['coupon' => $validation['error']]);
            }

            return response()->json([
                'success' => false,
                'message' => $validation['error'],
            ], 422);
        }

        // Get cart for subtotal calculation
        $cart = $this->getOrCreateCart();
        $subtotal = $cart ? $cart->subtotal : 0;

        // Calculate discount amount
        $discount = $promotionService->calculateDiscount($validation['coupon'], $subtotal);

        // Store coupon in session
        session([
            'applied_coupon' => [
                'code' => $code,
                'promotion_code_id' => $validation['promotion_code']->id,
                'coupon_id' => $validation['coupon']->id,
                'name' => $validation['coupon']->name,
                'discount_type' => $discount['type'],
                'discount_amount' => $discount['amount'],
                'discount_percentage' => $discount['percentage'],
                'description' => $discount['description'],
            ]
        ]);

        // Check if this is an Inertia request
        if ($request->header('X-Inertia')) {
            return back()->with('success', 'Coupon applied successfully.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Coupon applied successfully.',
            'coupon' => [
                'code' => $code,
                'name' => $validation['coupon']->name,
                'description' => $discount['description'],
                'discount_amount' => $discount['amount'],
            ],
        ]);
    }

    /**
     * Remove applied coupon
     */
    public function removeCoupon(Request $request)
    {
        session()->forget('applied_coupon');

        // Check if this is an Inertia request
        if ($request->header('X-Inertia')) {
            return back()->with('success', 'Coupon removed successfully.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Coupon removed successfully.',
        ]);
    }

    /**
     * orders.max_qty_per_line (0 = unlimited): the response to send when a line would hold more than the limit.
     */
    private function maxQuantityExceeded(int $quantity, bool $json = true)
    {
        $max = (int) settings('orders.max_qty_per_line', 0);

        if ($max <= 0 || $quantity <= $max) {
            return null;
        }

        $message = __('cart.max_qty_exceeded', ['max' => $max]);

        if ($json) {
            return response()->json([
                'success' => false,
                'error' => 'Maximum quantity per item exceeded',
                'message' => $message,
                'max' => $max,
            ], 422);
        }

        return back()->withErrors(['message' => $message, 'maxQuantity' => $max]);
    }

    /**
     * Get applied coupon from session
     */
    protected function getAppliedCoupon(): ?array
    {
        return session('applied_coupon');
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
