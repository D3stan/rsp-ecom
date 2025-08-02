<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutRequest;
use App\Services\CheckoutService;
use App\Models\Cart;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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

        // Get user's cart items
        $cartItems = Cart::where('user_id', $user->id)
            ->with(['product', 'size'])
            ->get();

        if ($cartItems->isEmpty()) {
            return redirect()->route('cart')->with('error', 'Your cart is empty');
        }

        // Calculate totals
        $totals = $this->checkoutService->calculateTotals($cartItems);

        // Get user's saved addresses (if Address model exists)
        $addresses = $user->addresses ?? collect();

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
            'paymentMethods' => $paymentMethods,
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
            $cartItems = Cart::where('user_id', $user->id)
                ->with(['product', 'size'])
                ->get();

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
    public function guestCheckout(Request $request)
    {
        // For now, redirect to login
        // In Phase 3, we'll implement proper guest checkout
        return redirect()->route('login')->with('message', 'Please log in to proceed with checkout');
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
