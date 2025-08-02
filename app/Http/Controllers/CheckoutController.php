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
            return redirect()->route('cart')->with('error', 'Your cart is empty');
        }

        // Get cart items with their relationships
        $cartItems = $cart->cartItems()->with(['product', 'size'])->get();

        if ($cartItems->isEmpty()) {
            return redirect()->route('cart')->with('error', 'Your cart is empty');
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
     * Create a Stripe checkout session
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

            // Create checkout session
            $session = $this->checkoutService->createCheckoutSession(
                $user,
                $cartItems,
                $request->validated()
            );

            return response()->json([
                'sessionId' => $session->id,
                'url' => $session->url
            ]);

        } catch (\Exception $e) {
            Log::error('Checkout session creation failed: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'Unable to create checkout session. Please try again.'
            ], 500);
        }
    }

    /**
     * Handle successful payment
     */
    public function success(Request $request)
    {
        $sessionId = $request->get('session_id');
        
        if (!$sessionId) {
            return redirect()->route('cart')->with('error', 'Invalid checkout session');
        }

        try {
            // Retrieve the checkout session
            $session = $this->checkoutService->retrieveCheckoutSession($sessionId);
            
            // Find the order
            $order = Order::where('stripe_checkout_session_id', $sessionId)->first();
            
            if (!$order) {
                return redirect()->route('cart')->with('error', 'Order not found');
            }

            // Update order status if payment succeeded
            if ($session->payment_status === 'paid') {
                $order->updatePaymentStatus('succeeded');
                
                // Clear user's cart
                if (Auth::check()) {
                    Cart::where('user_id', Auth::id())->delete();
                }
            }

            return Inertia::render('Checkout/Success', [
                'order' => $order->load(['orderItems.product', 'orderItems.size']),
                'session' => [
                    'id' => $session->id,
                    'payment_status' => $session->payment_status,
                    'customer_email' => $session->customer_details->email,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Checkout success handling failed: ' . $e->getMessage());
            
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
     * Handle guest checkout
     */
    public function guestCheckout(GuestCheckoutRequest $request)
    {
        // Get cart from session
        $sessionId = Session::getId();
        $cart = Cart::where('session_id', $sessionId)->first();
        
        // Verify cart session and get cart items
        if ($request->cart_session_id !== $sessionId) {
            return response()->json(['error' => 'Invalid cart session'], 400);
        }

        if (!$cart || $cart->cartItems->isEmpty()) {
            return response()->json(['error' => 'Cart is empty'], 400);
        }

        try {
            // Get cart items with relationships
            $cartItems = $cart->cartItems()->with(['product', 'size'])->get();
            
            // Create guest checkout session using existing cart items
            $session = $this->checkoutService->createGuestCheckoutSessionFromCart(
                $cartItems,
                $request->validated(),
                $sessionId
            );

            return response()->json([
                'checkout_url' => $session->url,
                'session_id' => $session->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Guest checkout failed: ' . $e->getMessage());
            return response()->json(['error' => 'Checkout failed. Please try again.'], 500);
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
}
