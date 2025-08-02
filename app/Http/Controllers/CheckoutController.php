<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Checkout;

class CheckoutController extends Controller
{
    /**
     * Product checkout using Cashier's built-in method
     */
    public function productCheckout(Request $request, string $priceId): RedirectResponse
    {
        try {
            $user = $request->user();
            
            // Use Cashier's built-in checkout method
            $checkoutSession = $user->checkout([$priceId => 1], [
                'success_url' => route('checkout.success') . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('checkout.cancel'),
            ]);
            
            return redirect($checkoutSession->url);
            
        } catch (\Exception $e) {
            Log::error('Product checkout failed: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Unable to create checkout session. Please try again.');
        }
    }

    /**
     * Product checkout with promotion codes
     */
    public function productCheckoutWithPromo(Request $request, string $priceId): RedirectResponse
    {
        try {
            $user = $request->user();
            
            // Use Cashier's built-in promotion code support
            $checkoutSession = $user
                ->allowPromotionCodes()
                ->checkout([$priceId => 1], [
                    'success_url' => route('checkout.success') . '?session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url' => route('checkout.cancel'),
                ]);
            
            return redirect($checkoutSession->url);
            
        } catch (\Exception $e) {
            Log::error('Product checkout with promo failed: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Unable to create checkout session. Please try again.');
        }
    }

    /**
     * Product checkout with tax ID collection
     */
    public function productCheckoutWithTax(Request $request, string $priceId): RedirectResponse
    {
        try {
            $user = $request->user();
            
            // Use Cashier's built-in tax ID collection
            $checkoutSession = $user
                ->collectTaxIds()
                ->checkout([$priceId => 1], [
                    'success_url' => route('checkout.success') . '?session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url' => route('checkout.cancel'),
                ]);
            
            return redirect($checkoutSession->url);
            
        } catch (\Exception $e) {
            Log::error('Product checkout with tax failed: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Unable to create checkout session. Please try again.');
        }
    }

    /**
     * Single charge checkout using Cashier's built-in method
     */
    public function singleChargeCheckout(Request $request, int $amount, string $name): RedirectResponse
    {
        try {
            $user = $request->user();
            
            // Use Cashier's built-in checkoutCharge method
            $checkoutSession = $user->checkoutCharge($amount, $name, 1, [
                'success_url' => route('checkout.success') . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('checkout.cancel'),
            ]);
            
            return redirect($checkoutSession->url);
            
        } catch (\Exception $e) {
            Log::error('Single charge checkout failed: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Unable to create checkout session. Please try again.');
        }
    }

    /**
     * Guest checkout using Cashier's built-in method
     */
    public function guestCheckout(Request $request, string $priceId): RedirectResponse
    {
        try {
            // Use Cashier's built-in guest checkout
            $checkoutSession = Checkout::guest()->create($priceId, [
                'success_url' => route('checkout.success'),
                'cancel_url' => route('checkout.cancel'),
            ]);
            
            return redirect($checkoutSession->url);
            
        } catch (\Exception $e) {
            Log::error('Guest checkout failed: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Unable to process guest checkout. Please try again.');
        }
    }

    /**
     * Guest checkout with promotion code
     */
    public function guestCheckoutWithPromo(Request $request, string $priceId, string $promoCode): RedirectResponse
    {
        try {
            // Use Cashier's built-in guest checkout with promotion
            $checkoutSession = Checkout::guest()
                ->withPromotionCode($promoCode)
                ->create($priceId, [
                    'success_url' => route('checkout.success'),
                    'cancel_url' => route('checkout.cancel'),
                ]);
            
            return redirect($checkoutSession->url);
            
        } catch (\Exception $e) {
            Log::error('Guest checkout with promo failed: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Unable to process guest checkout. Please try again.');
        }
    }

    /**
     * Handle successful checkout
     */
    public function success(Request $request): Response
    {
        $sessionId = $request->get('session_id');
        
        if (!$sessionId) {
            return redirect()->route('home')->with('error', 'Invalid checkout session.');
        }

        try {
            $checkoutSession = null;
            
            // Retrieve checkout session if user is authenticated
            if ($request->user()) {
                $checkoutSession = $request->user()->stripe()->checkout->sessions->retrieve($sessionId);
            }
            
            return Inertia::render('Checkout/Success', [
                'sessionId' => $sessionId,
                'checkoutSession' => $checkoutSession,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Checkout success handling failed: ' . $e->getMessage());
            return redirect()->route('home')->with('error', 'There was an issue processing your order.');
        }
    }

    /**
     * Handle cancelled checkout
     */
    public function cancel(Request $request): Response
    {
        return Inertia::render('Checkout/Cancel');
    }

    /**
     * Cart checkout - converts cart items to Stripe checkout
     * This bridges the gap between cart-based workflow and Cashier's product-based checkout
     */
    public function cartCheckout(Request $request): RedirectResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return redirect()->route('guest.cart.checkout');
            }

            // Get user's cart (using the correct relationship method)
            $cart = $user->carts()->with(['cartItems.product', 'cartItems.size'])->latest()->first();
            
            if (!$cart || $cart->cartItems->isEmpty()) {
                return redirect()->route('cart')->with('error', 'Your cart is empty.');
            }

            // Calculate total amount for the entire cart
            $totalAmount = $cart->cartItems->sum(function ($item) {
                return $item->price * $item->quantity;
            });

            // Convert to cents for Stripe
            $totalAmountCents = (int)($totalAmount * 100);

            // Create description of cart contents
            $description = $cart->cartItems->map(function($item) {
                $sizeName = $item->size ? " ({$item->size->name})" : '';
                return $item->quantity . 'x ' . $item->product->name . $sizeName;
            })->join(', ');

            // Use Cashier's checkoutCharge for the entire cart
            $checkoutSession = $user->checkoutCharge($totalAmountCents, 'Cart Purchase', 1, [
                'success_url' => route('checkout.success') . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('checkout.cancel'),
                'metadata' => [
                    'cart_id' => $cart->id,
                    'user_id' => $user->id,
                    'type' => 'cart_checkout',
                    'description' => $description,
                ]
            ]);
            
            return redirect($checkoutSession->url);
            
        } catch (\Exception $e) {
            Log::error('Cart checkout failed: ' . $e->getMessage(), [
                'user_id' => $request->user()?->id,
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->back()->with('error', 'Unable to create checkout session. Please try again.');
        }
    }

    /**
     * Guest cart checkout - for non-authenticated users
     */
    public function guestCartCheckout(Request $request): RedirectResponse
    {
        try {
            // Get guest cart from database using session ID
            $guestSessionId = session()->getId();
            $cart = \App\Models\Cart::where('session_id', $guestSessionId)->with(['cartItems.product', 'cartItems.size'])->first();
            
            if (!$cart || $cart->cartItems->isEmpty()) {
                return redirect()->route('cart')->with('error', 'Your cart is empty.');
            }

            // Calculate total amount from cart items
            $totalAmount = $cart->cartItems->sum(function ($item) {
                return $item->price * $item->quantity;
            });

            // Convert to cents for Stripe
            $totalAmountCents = (int)($totalAmount * 100);

            // Create description from cart items
            $description = $cart->cartItems->map(function($item) {
                $sizeName = $item->size ? " ({$item->size->name})" : '';
                return $item->quantity . 'x ' . $item->product->name . $sizeName;
            })->join(', ');

            // For guest checkout, we need to create a temporary price in Stripe
            // or use Stripe's direct API since Cashier's guest checkout expects a price ID
            $stripe = new \Stripe\StripeClient(config('cashier.secret'));
            
            $checkoutSession = $stripe->checkout->sessions->create([
                'line_items' => [[
                    'price_data' => [
                        'currency' => config('cashier.currency', 'usd'),
                        'product_data' => [
                            'name' => 'Cart Purchase',
                            'description' => $description,
                        ],
                        'unit_amount' => $totalAmountCents,
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => route('checkout.success'),
                'cancel_url' => route('checkout.cancel'),
                'metadata' => [
                    'guest_session_id' => $guestSessionId,
                    'type' => 'guest_cart_checkout',
                    'description' => $description,
                ]
            ]);
            
            return redirect($checkoutSession->url);
            
        } catch (\Exception $e) {
            Log::error('Guest cart checkout failed: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Unable to process guest checkout. Please try again.');
        }
    }
}