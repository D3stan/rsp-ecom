<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutRequest;
use App\Http\Requests\GuestCheckoutRequest;
use App\Services\CheckoutService;
use App\Services\GuestCartService;
use App\Models\Cart;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Laravel\Cashier\Checkout;
use Laravel\Cashier\Exceptions\IncompletePayment;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class CheckoutController extends Controller
{
    protected CheckoutService $checkoutService;

    public function __construct(CheckoutService $checkoutService)
    {
        $this->checkoutService = $checkoutService;
    }

    /**
     * Display the checkout page
     */
    public function index()
    {
        $user = Auth::user();
        
        if (!$user) {
            return redirect()->route('login');
        }

        // Get user's cart with cart items
        $cart = Cart::where('user_id', $user->id)->first();
        
        if (!$cart) {
            return redirect()->route('cart')->with('error', 'Your cart is empty.');
        }

        // Get cart items with their relationships
        $cartItems = $cart->cartItems()->with(['product', 'size'])->get();

        if ($cartItems->isEmpty()) {
            return redirect()->route('cart')->with('error', 'Your cart is empty.');
        }

        // Calculate totals
        $totals = $this->checkoutService->calculateTotals($cartItems);

        // Get user's saved addresses
        $addresses = $user->addresses()->orderBy('is_default', 'desc')->get();
        $defaultBillingAddress = $user->addresses()->billing()->default()->first();
        $defaultShippingAddress = $user->addresses()->shipping()->default()->first();

        // Get user's saved payment methods
        $paymentMethods = [];
        try {
            if ($user->hasStripeId()) {
                $paymentMethods = $user->paymentMethods()->map(function ($paymentMethod) {
                    return [
                        'id' => $paymentMethod->id,
                        'type' => $paymentMethod->type,
                        'card' => $paymentMethod->card ? [
                            'brand' => $paymentMethod->card->brand,
                            'last4' => $paymentMethod->card->last4,
                            'exp_month' => $paymentMethod->card->exp_month,
                            'exp_year' => $paymentMethod->card->exp_year,
                        ] : null,
                    ];
                });
            }
        } catch (\Exception $e) {
            Log::warning('Error fetching payment methods: ' . $e->getMessage());
        }

        return Inertia::render('Checkout/Index', [
            'cartItems' => $cartItems,
            'totals' => $totals,
            'addresses' => $addresses,
            'defaultBillingAddress' => $defaultBillingAddress,
            'defaultShippingAddress' => $defaultShippingAddress,
            'paymentMethods' => $paymentMethods,
            'user' => $user,
            'stripeKey' => config('cashier.key'),
        ]);
    }

    /**
     * Create a Stripe checkout session using Laravel Cashier
     */
    public function createSession(CheckoutRequest $request)
    {
        try {
            $user = Auth::user();
            $cart = Cart::where('user_id', $user->id)->first();

            if (!$cart) {
                return response()->json(['error' => 'Cart is empty'], 400);
            }

            $cartItems = $cart->cartItems()->with(['product', 'size'])->get();

            if ($cartItems->isEmpty()) {
                return response()->json(['error' => 'Cart is empty'], 400);
            }

            // Calculate totals
            $totals = $this->checkoutService->calculateTotals($cartItems);

            // For cart checkout, we'll use checkoutCharge for the total amount
            // This creates an ad-hoc product in Stripe for the entire cart
            $totalAmount = (int)($totals['total'] * 100); // Convert to cents

            // Create a description of cart contents
            $description = $cartItems->map(function($item) {
                return $item->quantity . 'x ' . $item->product->name . ' (' . $item->size->name . ')';
            })->join(', ');

            // Use Cashier's checkoutCharge method with enhanced features
            $checkout = $user->checkoutCharge($totalAmount, 'Cart Purchase - ' . $description, 1, [
                'success_url' => route('checkout.success') . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('checkout.cancel') . '?session_id={CHECKOUT_SESSION_ID}',
                'allow_promotion_codes' => true, // Enable Stripe promotion codes
                'billing_address_collection' => 'required',
                'shipping_address_collection' => [
                    'allowed_countries' => ['US', 'CA'], // Limit to US and Canada for now
                ],
                'phone_number_collection' => [
                    'enabled' => true,
                ],
                'tax_id_collection' => [
                    'enabled' => true,
                ],
                'metadata' => [
                    'cart_id' => $cart->id,
                    'user_id' => $user->id,
                    'cart_items' => $cartItems->count(),
                    'description' => $description,
                ]
            ]);

            // Create order record before redirecting
            $order = $this->checkoutService->createOrderFromCheckout($user, $cartItems, $checkout, $request->validated());

            // Redirect to Stripe checkout
            return response()->json(['checkout_url' => $checkout->url]);

        } catch (IncompletePayment $e) {
            // Handle incomplete payments
            return redirect()->route('cashier.payment', [
                $e->payment->id, 
                'redirect' => route('home')
            ]);
        } catch (\Exception $e) {
            Log::error('Checkout session creation failed: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'exception' => $e
            ]);
            
            return response()->json(['error' => 'Unable to create checkout session. Please try again.'], 500);
        }
    }

    /**
     * Handle successful payment using Laravel Cashier
     */
    public function success(Request $request)
    {
        $sessionId = $request->get('session_id');
        
        if (!$sessionId) {
            return redirect()->route('cart')->with('error', 'Invalid checkout session');
        }

        try {
            // Find the order by session ID
            $order = Order::where('stripe_checkout_session_id', $sessionId)->first();
            
            if (!$order) {
                return redirect()->route('cart')->with('error', 'Order not found');
            }

            // Retrieve session from Stripe using Cashier (through the user if available)
            $session = null;
            if ($order->user_id) {
                // Authenticated user checkout
                $user = $order->user;
                $session = $user->stripe()->checkout->sessions->retrieve($sessionId);
                
                // Clear user's cart on successful payment
                if ($session->payment_status === 'paid') {
                    Cart::where('user_id', $user->id)->delete();
                }
            } else {
                // Guest checkout - use direct Stripe API
                $session = \Stripe\Checkout\Session::retrieve($sessionId);

                // Clear guest cart on successful payment
                if ($session->payment_status === 'paid') {
                    $guestCartService = app(GuestCartService::class);
                    $guestCartService->clearCart();
                }
            }

            // Update order status based on payment status
            if ($session->payment_status === 'paid') {
                $order->update([
                    'payment_status' => 'succeeded',
                    'status' => 'processing',
                ]);
            }

            return Inertia::render('Checkout/Success', [
                'order' => $order->load(['orderItems.product', 'orderItems.size']),
                'session' => [
                    'id' => $session->id,
                    'payment_status' => $session->payment_status,
                    'customer_email' => $session->customer_details?->email ?? $order->guest_email,
                    'amount_total' => $session->amount_total,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Checkout success handling failed: ' . $e->getMessage(), [
                'session_id' => $sessionId,
                'exception' => $e
            ]);
            
            return redirect()->route('cart')->with('error', 'Unable to process your order. Please contact support.');
        }
    }

    /**
     * Handle cancelled payment
     */
    public function cancel(Request $request)
    {
        $sessionId = $request->get('session_id');
        
        // Update order status if session exists
        if ($sessionId) {
            $order = Order::where('stripe_checkout_session_id', $sessionId)->first();
            if ($order) {
                $order->updatePaymentStatus('cancelled');
            }
        }

        return Inertia::render('Checkout/Cancel', [
            'message' => 'Your payment was cancelled. You can try again anytime.'
        ]);
    }

    /**
     * Display checkout session details
     */
    public function show(Request $request)
    {
        $sessionId = $request->get('session_id');
        
        if (!$sessionId) {
            return redirect()->route('checkout');
        }

        try {
            $session = $this->checkoutService->retrieveCheckoutSession($sessionId);
            $order = Order::where('stripe_checkout_session_id', $sessionId)->first();

            return Inertia::render('Checkout/Show', [
                'session' => [
                    'id' => $session->id,
                    'status' => $session->status,
                    'payment_status' => $session->payment_status,
                    'customer_email' => $session->customer_details->email ?? null,
                ],
                'order' => $order ? $order->load(['orderItems.product', 'orderItems.size']) : null,
            ]);

        } catch (\Exception $e) {
            Log::error('Checkout session retrieval failed: ' . $e->getMessage());
            
            return redirect()->route('checkout')->with('error', 'Unable to retrieve checkout session');
        }
    }

    /**
     * Handle guest checkout using Laravel Cashier
     */
    public function guestCheckout(GuestCheckoutRequest $request)
    {
        try {
            // Get guest cart service
            $guestCartService = app(GuestCartService::class);
            $cartItems = $guestCartService->getCartItems();

            if (empty($cartItems)) {
                return response()->json(['error' => 'Cart is empty'], 400);
            }

            // Calculate totals
            $totals = $guestCartService->calculateTotals();
            $totalAmount = (int)($totals['total'] * 100); // Convert to cents

            // Create a description of cart contents
            $description = collect($cartItems)->map(function($item) {
                return $item['quantity'] . 'x ' . $item['product']['name'] . ' (' . $item['size']['name'] . ')';
            })->join(', ');

            // Get guest session ID
            $guestSessionId = $guestCartService->getGuestSessionId();

            // Use Cashier's guest checkout with enhanced features
            $checkout = \Laravel\Cashier\Checkout::guest()->create($totalAmount, [
                'product_data' => [
                    'name' => 'Cart Purchase',
                    'description' => $description,
                ],
                'success_url' => route('checkout.success') . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('checkout.cancel') . '?session_id={CHECKOUT_SESSION_ID}',
                'customer_email' => $request->guest_email,
                'allow_promotion_codes' => true, // Enable Stripe promotion codes
                'billing_address_collection' => 'required',
                'shipping_address_collection' => [
                    'allowed_countries' => ['US', 'CA'], // Limit to US and Canada for now
                ],
                'phone_number_collection' => [
                    'enabled' => true,
                ],
                'tax_id_collection' => [
                    'enabled' => true,
                ],
                'metadata' => [
                    'guest_session_id' => $guestSessionId,
                    'cart_items' => count($cartItems),
                    'is_guest' => 'true',
                    'guest_checkout' => 'true',
                ]
            ]);

            // Create guest order record
            $order = $this->checkoutService->createGuestOrderFromCheckout($cartItems, $checkout, $request->validated(), $guestSessionId);

            return response()->json(['checkout_url' => $checkout->url]);

        } catch (\Exception $e) {
            Log::error('Guest checkout failed: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Unable to create checkout session. Please try again.'], 500);
        }
    }

    /**
     * Display guest checkout page
     */
    public function guestIndex(Request $request)
    {
        // Get cart items from session (for guest users)
        $sessionId = Session::getId();
        $cart = Cart::where('session_id', $sessionId)->first();
        
        if (!$cart || $cart->cartItems->isEmpty()) {
            return redirect()->route('cart')->with('error', 'Your cart is empty');
        }

        // Get cart items with products and sizes
        $cartItems = $cart->cartItems()->with(['product', 'size'])->get();
        
        // Calculate totals using the existing CheckoutService
        $totals = $this->checkoutService->calculateTotals($cartItems);

        return Inertia::render('Checkout/GuestIndex', [
            'cartItems' => $cartItems->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product' => [
                        'id' => $item->product->id,
                        'name' => $item->product->name,
                        'slug' => $item->product->slug,
                        'price' => $item->product->price,
                        'images' => $item->product->images ?? [],
                    ],
                    'size' => $item->size ? [
                        'id' => $item->size->id,
                        'name' => $item->size->name,
                        'price' => $item->size->price,
                    ] : null,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'total' => $item->total,
                ];
            }),
            'totals' => [
                'subtotal' => $totals['subtotal'],
                'tax_amount' => $totals['tax_amount'],
                'shipping_cost' => $totals['shipping_cost'],
                'total' => $totals['total'],
            ],
            'guestSessionId' => $sessionId,
            'stripeKey' => config('cashier.key'),
        ]);
    }

    /**
     * Handle Stripe webhooks
     */
    public function webhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $endpoint_secret = config('cashier.webhook.secret');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $endpoint_secret);
        } catch (\UnexpectedValueException $e) {
            Log::error('Invalid webhook payload: ' . $e->getMessage());
            return response('Invalid payload', 400);
        } catch (SignatureVerificationException $e) {
            Log::error('Invalid webhook signature: ' . $e->getMessage());
            return response('Invalid signature', 400);
        }

        // Handle the event
        try {
            $this->checkoutService->handleWebhookEvent($event);
            
            return response('Webhook handled', 200);
        } catch (\Exception $e) {
            Log::error('Webhook handling failed: ' . $e->getMessage());
            return response('Webhook handling failed', 500);
        }
    }

    /**
     * Individual product checkout using Stripe Price IDs (proper Cashier way)
     */
    public function productCheckout(Request $request, $productId)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1|max:10',
            'size_id' => 'required|exists:sizes,id'
        ]);

        try {
            $user = Auth::user();
            $product = \App\Models\Product::findOrFail($productId);
            
            // For individual products, we would ideally have Stripe Price IDs stored
            // But since this is an e-commerce catalog, we'll use checkoutCharge for individual items too
            $quantity = $request->quantity;
            $amount = (int)($product->price * $quantity * 100); // Convert to cents
            
            $checkout = $user->checkoutCharge($amount, $product->name, $quantity, [
                'success_url' => route('checkout.success') . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('checkout.cancel') . '?session_id={CHECKOUT_SESSION_ID}',
                'allow_promotion_codes' => true, // Enable Stripe promotion codes
                'billing_address_collection' => 'required',
                'shipping_address_collection' => [
                    'allowed_countries' => ['US', 'CA'], // Limit to US and Canada for now
                ],
                'phone_number_collection' => [
                    'enabled' => true,
                ],
                'tax_id_collection' => [
                    'enabled' => true,
                ],
                'metadata' => [
                    'product_id' => $product->id,
                    'size_id' => $request->size_id,
                    'quantity' => $quantity,
                    'single_product' => 'true',
                ]
            ]);

            // Create order record for single product
            $order = Order::create([
                'user_id' => $user->id,
                'order_number' => 'PROD-' . time() . '-' . $user->id,
                'status' => 'pending',
                'subtotal' => $product->price * $quantity,
                'tax_amount' => round($product->price * $quantity * 0.0875, 2),
                'shipping_amount' => ($product->price * $quantity) >= 100 ? 0 : 10,
                'total_amount' => $amount / 100,
                'currency' => 'usd',
                'stripe_checkout_session_id' => $checkout->id,
                'payment_status' => 'pending',
                'payment_method' => 'stripe_checkout',
                'stripe_customer_id' => $user->stripe_id,
            ]);

            // Create single order item
            $order->orderItems()->create([
                'product_id' => $product->id,
                'size_id' => $request->size_id,
                'quantity' => $quantity,
                'price' => $product->price,
                'total' => $product->price * $quantity,
            ]);

            return response()->json(['checkout_url' => $checkout->url]);

        } catch (\Exception $e) {
            Log::error('Product checkout failed: ' . $e->getMessage(), [
                'product_id' => $productId,
                'user_id' => Auth::id(),
                'exception' => $e
            ]);
            
            return response()->json(['error' => 'Unable to create checkout session. Please try again.'], 500);
        }
    }
}
