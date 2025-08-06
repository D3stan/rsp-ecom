<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Checkout;
use App\Models\Order;
use App\Models\Address;
use App\Models\Cart;
use App\Models\User;

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

        if (!$sessionId) {
            Log::warning('No session_id provided to success page', [
                'url' => $request->fullUrl(),
                'all_params' => $request->all(),
            ]);
            return redirect()->route('home')->with('error', 'Invalid checkout session.');
        }

        try {
            // Initialize Stripe client
            $stripe = new \Stripe\StripeClient(env('STRIPE_SECRET'));
            
            // Retrieve checkout session from Stripe (works for both user and guest sessions)
            $checkoutSession = $stripe->checkout->sessions->retrieve($sessionId);
            
            // Find the order in our database by session ID
            $order = Order::where('stripe_checkout_session_id', $sessionId)
                ->with(['orderItems.product', 'orderItems.product.sizes'])
                ->first();
            
            // If no order found, create one from the session data
            if (!$order) {
                Log::info('Creating order from Stripe session', [
                    'session_id' => $sessionId,
                    'payment_status' => $checkoutSession->payment_status
                ]);
                
                // Create order from session data
                $order = $this->createOrderFromSession($checkoutSession, $request->user());
                $orderData = $order;
            } else {
                $orderData = $order;
            }
            
            // Send confirmation email if order exists and email hasn't been sent yet
            if ($order && $order->payment_status === 'succeeded' && !$order->hasEmailBeenSent()) {
                $emailService = app(\App\Services\EmailService::class);
                $emailSent = $emailService->sendOrderConfirmation($order);
                
                if ($emailSent) {
                    Log::info('Order confirmation email sent from success page', ['order_id' => $order->id]);
                } else {
                    Log::warning('Failed to send order confirmation email from success page', ['order_id' => $order->id]);
                }
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
     * Create order from Stripe checkout session
     */
    private function createOrderFromSession(object $checkoutSession, ?User $user): Order
    {
        try {
            // Extract metadata
            $metadata = $checkoutSession->metadata ?? (object)[];
            $isGuestOrder = !$user || !isset($metadata->user_id);
            
            // Find the cart based on metadata
            $cart = null;
            if (isset($metadata->cart_id)) {
                $cart = Cart::with(['cartItems.product', 'cartItems.size'])->find($metadata->cart_id);
            } elseif (isset($metadata->guest_session_id)) {
                $cart = Cart::where('session_id', $metadata->guest_session_id)
                    ->with(['cartItems.product', 'cartItems.size'])
                    ->first();
            }

            // Parse shipping address from metadata if available
            $shippingAddressString = $metadata->shipping_address ?? '';
            $addressParts = $this->parseShippingAddress($shippingAddressString);

            // Create addresses (simplified - you might want to create proper Address records)
            $billingAddress = $this->createAddressFromSession($checkoutSession, $addressParts, 'billing');
            $shippingAddress = $this->createAddressFromSession($checkoutSession, $addressParts, 'shipping');

            // Calculate totals from session
            $subtotal = ($checkoutSession->amount_subtotal ?? $checkoutSession->amount_total) / 100;
            $taxAmount = ($checkoutSession->total_details->amount_tax ?? 0) / 100;
            $shippingCost = ($checkoutSession->total_details->amount_shipping ?? 0) / 100;
            $totalAmount = $checkoutSession->amount_total / 100;

            // Create order
            $orderData = [
                'order_number' => Order::generateOrderNumber(),
                'user_id' => $isGuestOrder ? null : $user->id,
                'billing_address_id' => $billingAddress->id,
                'shipping_address_id' => $shippingAddress->id,
                'status' => 'processing',
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'shipping_amount' => $shippingCost,
                'total_amount' => $totalAmount,
                'currency' => strtoupper($checkoutSession->currency),
                'notes' => $metadata->order_notes ?? null,
                'stripe_checkout_session_id' => $checkoutSession->id,
                'stripe_payment_intent_id' => $checkoutSession->payment_intent ?? null,
                'payment_status' => $checkoutSession->payment_status === 'paid' ? 'succeeded' : 'processing',
                'payment_method' => 'stripe',
            ];

            // Add guest-specific fields
            if ($isGuestOrder) {
                $orderData['guest_email'] = $checkoutSession->customer_details->email ?? $metadata->guest_email ?? null;
                $orderData['guest_phone'] = $checkoutSession->customer_details->phone ?? null;
                $orderData['guest_session_id'] = $metadata->guest_session_id ?? null;
            }

            $order = Order::create($orderData);

            // Create order items from cart or extract from session
            if ($cart && $cart->cartItems->isNotEmpty()) {
                foreach ($cart->cartItems as $cartItem) {
                    $order->orderItems()->create([
                        'product_id' => $cartItem->product_id,
                        'product_name' => $cartItem->product->name,
                        'quantity' => $cartItem->quantity,
                        'price' => $cartItem->price,
                        'total' => $cartItem->price * $cartItem->quantity,
                    ]);
                }
                
                // Clear the cart after order creation
                $cart->cartItems()->delete();
                $cart->delete();
            } else {
                // Extract line items from session
                $this->createOrderItemsFromSession($order, $checkoutSession);
            }

            Log::info('Order created from Stripe session', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'session_id' => $checkoutSession->id,
                'is_guest' => $isGuestOrder,
                'total_amount' => $order->total_amount
            ]);

            return $order;

        } catch (\Exception $e) {
            Log::error('Failed to create order from Stripe session', [
                'session_id' => $checkoutSession->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Parse shipping address from metadata string
     */
    private function parseShippingAddress(string $addressString): array
    {
        // Expected format: "John Doe, 123 Main St, City, State ZIP, Country"
        $parts = array_map('trim', explode(', ', $addressString));
        
        return [
            'name' => $parts[0] ?? '',
            'address_line_1' => $parts[1] ?? '',
            'city' => $parts[2] ?? '',
            'state_postal' => $parts[3] ?? '',
            'country' => $parts[4] ?? ''
        ];
    }

    /**
     * Create address from session data
     */
    private function createAddressFromSession(object $session, array $addressParts, string $type): Address
    {
        $customerDetails = $session->customer_details ?? (object)[];
        $address = $customerDetails->address ?? (object)[];
        
        // Parse name
        $nameParts = explode(' ', $addressParts['name'] ?: ($customerDetails->name ?? ''), 2);
        $firstName = $nameParts[0] ?? '';
        $lastName = $nameParts[1] ?? '';
        
        // Parse state and postal code
        $statePostal = $addressParts['state_postal'] ?? '';
        $statePostalParts = explode(' ', $statePostal);
        $state = $statePostalParts[0] ?? '';
        $postalCode = $statePostalParts[1] ?? '';

        return Address::create([
            'type' => $type,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'company' => null,
            'address_line_1' => $addressParts['address_line_1'] ?: ($address->line1 ?? ''),
            'address_line_2' => $address->line2 ?? null,
            'city' => $addressParts['city'] ?: ($address->city ?? ''),
            'state' => $state ?: ($address->state ?? ''),
            'postal_code' => $postalCode ?: ($address->postal_code ?? ''),
            'country' => $addressParts['country'] ?: ($address->country ?? 'US'),
            'phone' => $customerDetails->phone ?? null,
        ]);
    }

    /**
     * Create order items from Stripe session line items
     */
    private function createOrderItemsFromSession(Order $order, object $checkoutSession): void
    {
        try {
            $stripe = new \Stripe\StripeClient(env('STRIPE_SECRET'));
            $lineItems = $stripe->checkout->sessions->allLineItems($checkoutSession->id);
            
            foreach ($lineItems->data as $item) {
                $order->orderItems()->create([
                    'product_id' => null, // We don't have product ID from line items
                    'product_name' => $item->description,
                    'quantity' => $item->quantity,
                    'price' => $item->price->unit_amount / 100,
                    'total' => ($item->price->unit_amount * $item->quantity) / 100,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to create order items from session: ' . $e->getMessage());
            // Create a fallback order item
            $order->orderItems()->create([
                'product_id' => null,
                'product_name' => 'Cart Purchase',
                'quantity' => 1,
                'price' => $order->total_amount,
                'total' => $order->total_amount,
            ]);
        }
    }

    /**
     * Extract line items from checkout session for temporary display
     */
    private function extractLineItemsFromSession(object $checkoutSession): array
    {
        try {
            $stripe = new \Stripe\StripeClient(env('STRIPE_SECRET'));
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
     * Show checkout details page for authenticated users
     */
    public function showDetails(Request $request): Response|RedirectResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return redirect()->route('guest.checkout.details');
            }

            // Get user's cart
            $cart = $user->carts()->with(['cartItems.product', 'cartItems.size'])->latest()->first();
            
            if (!$cart || $cart->cartItems->isEmpty()) {
                return redirect()->route('cart')->with('error', 'Your cart is empty.');
            }

            // Calculate totals
            $subtotal = $cart->cartItems->sum(function ($item) {
                return $item->price * $item->quantity;
            });
            
            $shippingCost = $this->calculateShippingCost($subtotal);
            $taxAmount = $this->calculateTaxAmount($subtotal);
            $discountAmount = 0; // TODO: Implement discount logic
            $total = $subtotal + $shippingCost + $taxAmount - $discountAmount;
            $totalItems = $cart->cartItems->sum('quantity');

            return Inertia::render('Checkout/Details', [
                'cart' => $cart,
                'cartItems' => $cart->cartItems,
                'subtotal' => $subtotal,
                'shippingCost' => $shippingCost,
                'taxAmount' => $taxAmount,
                'discountAmount' => $discountAmount,
                'total' => $total,
                'totalItems' => $totalItems,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to show checkout details: ' . $e->getMessage());
            return redirect()->route('cart')->with('error', 'Unable to load checkout page.');
        }
    }

    /**
     * Show checkout details page for guest users
     */
    public function showGuestDetails(Request $request): Response|RedirectResponse
    {
        try {
            // Get guest cart from database using session ID
            $guestSessionId = session()->getId();
            $cart = \App\Models\Cart::where('session_id', $guestSessionId)->with(['cartItems.product', 'cartItems.size'])->first();
            
            if (!$cart || $cart->cartItems->isEmpty()) {
                return redirect()->route('cart')->with('error', 'Your cart is empty.');
            }

            // Calculate totals
            $subtotal = $cart->cartItems->sum(function ($item) {
                return $item->price * $item->quantity;
            });
            
            $shippingCost = $this->calculateShippingCost($subtotal);
            $taxAmount = $this->calculateTaxAmount($subtotal);
            $discountAmount = 0; // TODO: Implement discount logic
            $total = $subtotal + $shippingCost + $taxAmount - $discountAmount;
            $totalItems = $cart->cartItems->sum('quantity');

            return Inertia::render('Checkout/Details', [
                'cart' => $cart,
                'cartItems' => $cart->cartItems,
                'subtotal' => $subtotal,
                'shippingCost' => $shippingCost,
                'taxAmount' => $taxAmount,
                'discountAmount' => $discountAmount,
                'total' => $total,
                'totalItems' => $totalItems,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to show guest checkout details: ' . $e->getMessage());
            return redirect()->route('cart')->with('error', 'Unable to load checkout page.');
        }
    }

    /**
     * Calculate shipping cost based on subtotal
     */
    private function calculateShippingCost(float $subtotal): float
    {
        // Free shipping for orders over $100
        if ($subtotal >= 100) {
            return 0;
        }
        
        // Standard shipping rate
        return 9.99;
    }

    /**
     * Calculate tax amount based on subtotal
     */
    private function calculateTaxAmount(float $subtotal): float
    {
        // Simple tax calculation - 8.5% tax rate
        return $subtotal * 0.085;
    }

    /**
     * Cart checkout - converts cart items to Stripe checkout
     * This bridges the gap between cart-based workflow and Cashier's product-based checkout
     */
    public function cartCheckout(Request $request): RedirectResponse|HttpResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return redirect()->route('guest.cart.checkout');
            }

            // Validate the checkout details
            $validated = $request->validate([
                'email' => 'required|email|max:255',
                'shipping_address' => 'required|array',
                'shipping_address.first_name' => 'required|string|max:255',
                'shipping_address.last_name' => 'required|string|max:255',
                'shipping_address.address_line_1' => 'required|string|max:255',
                'shipping_address.city' => 'required|string|max:255',
                'shipping_address.state' => 'required|string|max:255',
                'shipping_address.postal_code' => 'required|string|max:20',
                'shipping_address.country' => 'required|string|max:2',
                'order_notes' => 'nullable|string|max:500',
                'coupon_code' => 'nullable|string|max:50',
            ]);

            // Get user's cart (using the correct relationship method)
            $cart = $user->carts()->with(['cartItems.product', 'cartItems.size'])->latest()->first();
            
            if (!$cart || $cart->cartItems->isEmpty()) {
                return redirect()->route('cart')->with('error', 'Your cart is empty.');
            }

            // Calculate total amount for the entire cart
            $subtotal = $cart->cartItems->sum(function ($item) {
                return $item->price * $item->quantity;
            });
            
            $shippingCost = $this->calculateShippingCost($subtotal);
            $taxAmount = $this->calculateTaxAmount($subtotal);
            $totalAmount = $subtotal + $shippingCost + $taxAmount;

            // Convert to cents for Stripe
            $totalAmountCents = (int)($totalAmount * 100);

            // Create description of cart contents
            $description = $cart->cartItems->map(function($item) {
                $sizeName = $item->size ? " ({$item->size->name})" : '';
                return $item->quantity . 'x ' . $item->product->name . $sizeName;
            })->join(', ');

            // Prepare shipping address for metadata
            $shippingAddress = $validated['shipping_address'];
            $shippingAddressString = implode(', ', [
                $shippingAddress['first_name'] . ' ' . $shippingAddress['last_name'],
                $shippingAddress['address_line_1'],
                $shippingAddress['city'],
                $shippingAddress['state'] . ' ' . $shippingAddress['postal_code'],
                $shippingAddress['country']
            ]);

            // Use Cashier's checkoutCharge for the entire cart
            $checkoutSession = $user->checkoutCharge($totalAmountCents, 'Cart Purchase', 1, [
                'success_url' => route('checkout.success') . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('checkout.cancel'),
                'metadata' => [
                    'cart_id' => $cart->id,
                    'user_id' => $user->id,
                    'user_email' => $validated['email'],
                    'type' => 'cart_checkout',
                    'description' => $description,
                    'shipping_address' => $shippingAddressString,
                    'order_notes' => $validated['order_notes'] ?? '',
                    'coupon_code' => $validated['coupon_code'] ?? '',
                ]
            ]);
            
            // For Inertia.js requests, return the URL as props instead of redirecting
            if ($request->header('X-Inertia')) {
                return Inertia::location($checkoutSession->url);
            }
            
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
    public function guestCartCheckout(Request $request): RedirectResponse|HttpResponse
    {
        try {
            // Validate the checkout details
            $validated = $request->validate([
                'email' => 'required|email|max:255',
                'shipping_address' => 'required|array',
                'shipping_address.first_name' => 'required|string|max:255',
                'shipping_address.last_name' => 'required|string|max:255',
                'shipping_address.address_line_1' => 'required|string|max:255',
                'shipping_address.city' => 'required|string|max:255',
                'shipping_address.state' => 'required|string|max:255',
                'shipping_address.postal_code' => 'required|string|max:20',
                'shipping_address.country' => 'required|string|max:2',
                'order_notes' => 'nullable|string|max:500',
                'coupon_code' => 'nullable|string|max:50',
            ]);

            // Get guest cart from database using session ID
            $guestSessionId = session()->getId();
            $cart = \App\Models\Cart::where('session_id', $guestSessionId)->with(['cartItems.product', 'cartItems.size'])->first();
            
            if (!$cart || $cart->cartItems->isEmpty()) {
                return redirect()->route('cart')->with('error', 'Your cart is empty.');
            }

            // Calculate total amount from cart items
            $subtotal = $cart->cartItems->sum(function ($item) {
                return $item->price * $item->quantity;
            });
            
            $shippingCost = $this->calculateShippingCost($subtotal);
            $taxAmount = $this->calculateTaxAmount($subtotal);
            $totalAmount = $subtotal + $shippingCost + $taxAmount;

            // Convert to cents for Stripe
            $totalAmountCents = (int)($totalAmount * 100);

            // Create description from cart items
            $description = $cart->cartItems->map(function($item) {
                $sizeName = $item->size ? " ({$item->size->name})" : '';
                return $item->quantity . 'x ' . $item->product->name . $sizeName;
            })->join(', ');

            // Prepare shipping address for metadata
            $shippingAddress = $validated['shipping_address'];
            $shippingAddressString = implode(', ', [
                $shippingAddress['first_name'] . ' ' . $shippingAddress['last_name'],
                $shippingAddress['address_line_1'],
                $shippingAddress['city'],
                $shippingAddress['state'] . ' ' . $shippingAddress['postal_code'],
                $shippingAddress['country']
            ]);

            // For guest checkout, we need to create a temporary price in Stripe
            // or use Stripe's direct API since Cashier's guest checkout expects a price ID
            $stripe = new \Stripe\StripeClient(env('STRIPE_SECRET'));
            
            $checkoutSession = $stripe->checkout->sessions->create([
                'line_items' => [[
                    'price_data' => [
                        'currency' => env('CASHIER_CURRENCY', 'eur'),
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
                'customer_email' => $validated['email'],
                'metadata' => [
                    'guest_session_id' => $guestSessionId,
                    'guest_email' => $validated['email'],
                    'type' => 'guest_cart_checkout',
                    'description' => $description,
                    'shipping_address' => $shippingAddressString,
                    'order_notes' => $validated['order_notes'] ?? '',
                    'coupon_code' => $validated['coupon_code'] ?? '',
                ]
            ]);

            // For Inertia.js requests, return the URL as props instead of redirecting
            if ($request->header('X-Inertia')) {
                return Inertia::location($checkoutSession->url);
            }
            
            return redirect($checkoutSession->url);
            
        } catch (\Exception $e) {
            Log::error('Guest cart checkout failed: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Unable to process guest checkout. Please try again.');
        }
    }
}