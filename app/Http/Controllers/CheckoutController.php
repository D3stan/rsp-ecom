<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Checkout;
use App\Models\Order;

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
                'success_url' => route('checkout.success') . '?session_id={CHECKOUT_SESSION_ID}',
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
                    'success_url' => route('checkout.success') . '?session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url' => route('checkout.cancel'),
                ]);
            
            return redirect($checkoutSession->url);
            
        } catch (\Exception $e) {
            Log::error('Guest checkout with promo failed: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Unable to process guest checkout. Please try again.');
        }
    }

    /**
     * Handle successful checkout - works for both authenticated and guest users
     */
    public function success(Request $request): Response|RedirectResponse
    {
        $sessionId = $request->get('session_id');
        
        Log::info('Checkout success accessed', [
            'session_id' => $sessionId,
            'all_params' => $request->all(),
            'user_id' => $request->user()?->id,
        ]);
        
        if (!$sessionId) {
            Log::warning('No session_id provided to success page', [
                'url' => $request->fullUrl(),
                'all_params' => $request->all(),
            ]);
            return redirect()->route('home')->with('error', 'Invalid checkout session.');
        }

        try {
            // Initialize Stripe client
            $stripe = new \Stripe\StripeClient(config('cashier.secret'));
            
            // Retrieve checkout session from Stripe (works for both user and guest sessions)
            $checkoutSession = $stripe->checkout->sessions->retrieve($sessionId);
            
            // Find the order in our database by session ID
            $order = Order::where('stripe_checkout_session_id', $sessionId)
                ->with(['orderItems.product', 'orderItems.product.sizes'])
                ->first();
            
            // If no order found yet, it might still be processing via webhook
            // Create a basic order structure from the session data
            if (!$order) {
                Log::warning('Order not found for session, webhook may still be processing', [
                    'session_id' => $sessionId,
                    'payment_status' => $checkoutSession->payment_status
                ]);
                
                // Create a temporary order data structure for display
                $orderData = $this->createTemporaryOrderData($checkoutSession);
            } else {
                $orderData = $order;
            }
            
            // Determine if this is a guest checkout
            $isGuest = !$request->user() || ($order && $order->isGuestOrder());
            
            return Inertia::render('Checkout/Success', [
                'order' => $orderData,
                'session' => [
                    'id' => $checkoutSession->id,
                    'payment_status' => $checkoutSession->payment_status,
                    'customer_email' => $checkoutSession->customer_details->email ?? $checkoutSession->metadata->guest_email ?? 'N/A',
                    'amount_total' => $checkoutSession->amount_total,
                    'currency' => $checkoutSession->currency,
                    'created' => $checkoutSession->created,
                ],
                'isGuest' => $isGuest,
                'user' => $request->user(),
            ]);
            
        } catch (\Exception $e) {
            Log::error('Checkout success handling failed: ' . $e->getMessage(), [
                'session_id' => $sessionId,
                'user_id' => $request->user()?->id,
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->route('home')->with('error', 'There was an issue processing your order. Please contact support if you were charged.');
        }
    }

    /**
     * Create temporary order data when order is not yet created via webhook
     */
    private function createTemporaryOrderData(object $checkoutSession): array
    {
        return [
            'id' => 'pending',
            'total_amount' => $checkoutSession->amount_total / 100, // Convert from cents
            'subtotal' => $checkoutSession->amount_subtotal / 100,
            'tax_amount' => ($checkoutSession->total_details->amount_tax ?? 0) / 100,
            'shipping_cost' => ($checkoutSession->total_details->amount_shipping ?? 0) / 100,
            'currency' => strtoupper($checkoutSession->currency),
            'status' => 'processing',
            'payment_status' => $checkoutSession->payment_status,
            'created_at' => date('Y-m-d H:i:s', $checkoutSession->created),
            'orderItems' => $this->extractLineItemsFromSession($checkoutSession),
        ];
    }

    /**
     * Extract line items from checkout session for temporary display
     */
    private function extractLineItemsFromSession(object $checkoutSession): array
    {
        try {
            $stripe = new \Stripe\StripeClient(config('cashier.secret'));
            $lineItems = $stripe->checkout->sessions->allLineItems($checkoutSession->id);
            
            $items = [];
            foreach ($lineItems->data as $item) {
                $items[] = [
                    'id' => 'temp_' . uniqid(),
                    'quantity' => $item->quantity,
                    'price' => $item->price->unit_amount / 100,
                    'total' => ($item->price->unit_amount * $item->quantity) / 100,
                    'product' => [
                        'id' => 'temp',
                        'name' => $item->description,
                        'price' => $item->price->unit_amount / 100,
                        'image_url' => null,
                    ],
                    'size' => null,
                ];
            }
            
            return $items;
        } catch (\Exception $e) {
            Log::error('Failed to extract line items from session: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Handle cancelled checkout
     */
    public function cancel(Request $request): Response
    {
        $message = $request->get('message', 'Your payment was cancelled. You can try again anytime.');
        
        return Inertia::render('Checkout/Cancel', [
            'message' => $message,
        ]);
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
                'success_url' => route('checkout.success') . '?session_id={CHECKOUT_SESSION_ID}',
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