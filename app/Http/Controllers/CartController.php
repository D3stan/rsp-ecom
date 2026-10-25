<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use App\Services\PromotionService;
use App\Support\CheckoutTotals;
use Artesaos\SEOTools\Facades\SEOMeta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;

class CartController extends Controller
{
    public function index()
    {
        SEOMeta::addMeta('robots', 'noindex,nofollow', 'name');
        SEOMeta::setTitle('Shopping Cart – '.settings('store.name'));

        $cart = $this->getOrCreateCart();

        if (! $cart) {
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

        foreach ($cartItems as $item) {
            $updated = false;

            if ($item->has_price_changed) {
                $item->price = $item->current_price;
                $updated = true;
            }

            if (! $item->size_id && $item->product->size_id) {
                $item->size_id = $item->product->size_id;
                $updated = true;
            }

            if ($updated) {
                $item->save();
            }
        }

        $cart->load(['cartItems.size', 'cartItems.productVariant']);

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

    public function add(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'product_variant_id' => 'required|exists:product_variants,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $variant = ProductVariant::findOrFail($validated['product_variant_id']);

        if ($variant->product_id != $validated['product_id']) {
            return response()->json(['error' => 'Invalid variant for product'], 422);
        }

        if (! $variant->canAddToCart($validated['quantity'])) {
            return response()->json([
                'error' => 'Not enough stock',
                'available' => $variant->stock_quantity,
            ], 422);
        }

        if ($exceeded = $this->maxQuantityExceeded($validated['quantity'])) {
            return $exceeded;
        }

        $cart = $this->getOrCreateCart();

        $cartItem = $cart->cartItems()
            ->where('product_id', $validated['product_id'])
            ->where('product_variant_id', $validated['product_variant_id'])
            ->first();

        if ($cartItem) {
            $newQuantity = $cartItem->quantity + $validated['quantity'];
            if (! $variant->canAddToCart($newQuantity)) {
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

    public function update(Request $request, CartItem $cartItem)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $cart = $this->getOrCreateCart();
        if ($cartItem->cart_id !== $cart->id) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cart item not found.',
                ], 404);
            }

            return back()->withErrors(['message' => 'Cart item not found.']);
        }

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

        return back();
    }

    public function remove(CartItem $cartItem)
    {
        $cart = $this->getOrCreateCart();
        if ($cartItem->cart_id !== $cart->id) {
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cart item not found.',
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

    public function count()
    {
        $cart = $this->getOrCreateCart();

        return response()->json([
            'count' => $cart ? $cart->total_items : 0,
        ]);
    }

    public function applyCoupon(Request $request)
    {
        $request->validate([
            'code' => 'required|string|max:50',
        ]);

        $code = $request->input('code');

        $promotionService = app(PromotionService::class);
        $validation = $promotionService->validatePromotionCode($code);

        if (! $validation['valid']) {
            if ($request->header('X-Inertia')) {
                return back()->withErrors(['coupon' => $validation['error']]);
            }

            return response()->json([
                'success' => false,
                'message' => $validation['error'],
            ], 422);
        }

        $cart = $this->getOrCreateCart();
        $subtotal = $cart ? $cart->subtotal : 0;

        $discount = $promotionService->calculateDiscount($validation['coupon'], $subtotal);

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
            ],
        ]);

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

    public function removeCoupon(Request $request)
    {
        session()->forget('applied_coupon');

        if ($request->header('X-Inertia')) {
            return back()->with('success', 'Coupon removed successfully.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Coupon removed successfully.',
        ]);
    }

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

    protected function getAppliedCoupon(): ?array
    {
        return session('applied_coupon');
    }

    private function getOrCreateCart(): ?Cart
    {
        if (Auth::check()) {
            $cart = Cart::firstOrCreate([
                'user_id' => Auth::id(),
            ]);
        } else {
            $sessionId = Session::getId();
            $cart = Cart::firstOrCreate([
                'session_id' => $sessionId,
            ]);
        }

        return $cart;
    }
}
