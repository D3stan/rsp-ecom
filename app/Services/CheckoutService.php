<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;
use Stripe\Price;
use Stripe\Product;
use Stripe\Exception\ApiErrorException;

class CheckoutService
{
    /**
     * Calculate order totals from cart items
     */
    public function calculateTotals(Collection $cartItems): array
    {
        $subtotal = 0;
        $totalQuantity = 0;

        foreach ($cartItems as $item) {
            $itemTotal = $item->product->price * $item->quantity;
            $subtotal += $itemTotal;
            $totalQuantity += $item->quantity;
        }

        // For now, use simple calculations
        // In Phase 3, we'll add tax calculation and shipping
        $taxRate = 0.0875; // 8.75% - This should come from config or be calculated based on address
        $taxAmount = round($subtotal * $taxRate, 2);
        $shippingCost = $this->calculateShipping($subtotal, $totalQuantity);
        $total = $subtotal + $taxAmount + $shippingCost;

        return [
            'subtotal' => round($subtotal, 2),
            'tax_amount' => $taxAmount,
            'tax_rate' => $taxRate,
            'shipping_cost' => $shippingCost,
            'total' => round($total, 2),
            'total_quantity' => $totalQuantity,
        ];
    }

    /**
     * Calculate shipping cost
     */
    protected function calculateShipping(float $subtotal, int $totalQuantity): float
    {
        // Simple shipping calculation
        // Free shipping over $100, otherwise $10
        if ($subtotal >= 100) {
            return 0.0;
        }

        return 10.0;
    }

    /**
     * Create a Stripe checkout session
     */
    public function createCheckoutSession(User $user, Collection $cartItems, array $checkoutData): Session
    {
        try {
            // Calculate totals
            $totals = $this->calculateTotals($cartItems);

            // Create or update Stripe customer
            if (!$user->hasStripeId()) {
                $user->createAsStripeCustomer([
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $checkoutData['billing_address']['phone'] ?? null,
                ]);
            }

            // Create line items for Stripe
            $lineItems = $this->createStripeLineItems($cartItems);

            // Prepare checkout session parameters
            $sessionParams = [
                'customer' => $user->stripe_id,
                'line_items' => $lineItems,
                'mode' => $checkoutData['checkout_mode'] ?? 'payment',
                'success_url' => $checkoutData['success_url'] . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $checkoutData['cancel_url'] . '?session_id={CHECKOUT_SESSION_ID}',
                'locale' => $checkoutData['locale'] ?? 'en',
                'allow_promotion_codes' => $checkoutData['allow_promotion_codes'] ?? true,
                'billing_address_collection' => 'required',
                'shipping_address_collection' => [
                    'allowed_countries' => ['US', 'CA', 'GB', 'DE', 'FR', 'ES', 'IT'],
                ],
                'phone_number_collection' => [
                    'enabled' => true,
                ],
                'metadata' => [
                    'user_id' => $user->id,
                    'cart_items_count' => $cartItems->count(),
                    'order_type' => 'cart_checkout',
                ],
            ];

            // Add tax ID collection if requested
            if ($checkoutData['collect_tax_id'] ?? false) {
                $sessionParams['tax_id_collection'] = ['enabled' => true];
            }

            // Add saved payment method if provided
            if (!empty($checkoutData['payment_method_id'])) {
                $sessionParams['payment_method_options'] = [
                    'card' => [
                        'setup_future_usage' => $checkoutData['save_payment_method'] ?? false ? 'on_session' : null,
                    ],
                ];
            }

            // Create the checkout session
            $session = Session::create($sessionParams);

            // Create order record
            $this->createOrderFromSession($user, $cartItems, $session, $totals, $checkoutData);

            return $session;

        } catch (ApiErrorException $e) {
            Log::error('Stripe API error during checkout session creation', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'stripe_code' => $e->getStripeCode(),
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::error('General error during checkout session creation', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);
            throw $e;
        }
    }

    /**
     * Create Stripe line items from cart items
     */
    protected function createStripeLineItems(Collection $cartItems): array
    {
        $lineItems = [];

        foreach ($cartItems as $item) {
            $product = $item->product;
            $size = $item->size;

            // Create product name with size if applicable
            $productName = $product->name;
            if ($size) {
                $productName .= ' - Size: ' . $size->name;
            }

            $lineItems[] = [
                'price_data' => [
                    'currency' => 'usd',
                    'product_data' => [
                        'name' => $productName,
                        'description' => $product->description ? substr($product->description, 0, 300) : null,
                        'images' => $product->image_url ? [$product->image_url] : [],
                        'metadata' => [
                            'product_id' => $product->id,
                            'size_id' => $size?->id,
                            'sku' => $product->sku ?? $product->slug,
                        ],
                    ],
                    'unit_amount' => (int)($product->price * 100), // Convert to cents
                ],
                'quantity' => $item->quantity,
            ];
        }

        return $lineItems;
    }

    /**
     * Create order record from checkout session
     */
    protected function createOrderFromSession(
        User $user,
        Collection $cartItems,
        Session $session,
        array $totals,
        array $checkoutData
    ): Order {
        return DB::transaction(function () use ($user, $cartItems, $session, $totals, $checkoutData) {
            // Create the order
            $order = Order::create([
                'user_id' => $user->id,
                'total_amount' => $totals['total'],
                'status' => 'pending',
                'stripe_checkout_session_id' => $session->id,
                'stripe_customer_id' => $user->stripe_id,
                'payment_status' => 'pending',
                'payment_method' => 'stripe',
                'subtotal' => $totals['subtotal'],
                'tax_amount' => $totals['tax_amount'],
                'shipping_cost' => $totals['shipping_cost'],
                'billing_address' => json_encode($checkoutData['billing_address']),
                'shipping_address' => json_encode(
                    $checkoutData['shipping_same_as_billing'] 
                        ? $checkoutData['billing_address']
                        : $checkoutData['shipping_address']
                ),
            ]);

            // Create order items
            foreach ($cartItems as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'size_id' => $item->size_id,
                    'quantity' => $item->quantity,
                    'price' => $item->product->price,
                    'total' => $item->product->price * $item->quantity,
                ]);
            }

            return $order;
        });
    }

    /**
     * Retrieve a Stripe checkout session
     */
    public function retrieveCheckoutSession(string $sessionId): Session
    {
        try {
            return Session::retrieve($sessionId);
        } catch (ApiErrorException $e) {
            Log::error('Failed to retrieve Stripe checkout session', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Handle Stripe webhook events
     */
    public function handleWebhookEvent(object $event): void
    {
        Log::info('Processing Stripe webhook event', [
            'type' => $event->type,
            'id' => $event->id,
        ]);

        switch ($event->type) {
            case 'checkout.session.completed':
                $this->handleCheckoutSessionCompleted($event->data->object);
                break;

            case 'payment_intent.succeeded':
                $this->handlePaymentIntentSucceeded($event->data->object);
                break;

            case 'payment_intent.payment_failed':
                $this->handlePaymentIntentFailed($event->data->object);
                break;

            case 'checkout.session.expired':
                $this->handleCheckoutSessionExpired($event->data->object);
                break;

            default:
                Log::info('Unhandled webhook event type: ' . $event->type);
        }
    }

    /**
     * Handle checkout session completed webhook
     */
    protected function handleCheckoutSessionCompleted(object $session): void
    {
        $order = Order::where('stripe_checkout_session_id', $session->id)->first();

        if (!$order) {
            Log::warning('Order not found for completed checkout session', [
                'session_id' => $session->id,
            ]);
            return;
        }

        // Update order with payment intent ID
        $order->update([
            'stripe_payment_intent_id' => $session->payment_intent,
            'payment_status' => 'processing',
        ]);

        Log::info('Updated order from checkout session completed', [
            'order_id' => $order->id,
            'session_id' => $session->id,
        ]);
    }

    /**
     * Handle payment intent succeeded webhook
     */
    protected function handlePaymentIntentSucceeded(object $paymentIntent): void
    {
        $order = Order::where('stripe_payment_intent_id', $paymentIntent->id)->first();

        if (!$order) {
            Log::warning('Order not found for succeeded payment intent', [
                'payment_intent_id' => $paymentIntent->id,
            ]);
            return;
        }

        $order->updatePaymentStatus('succeeded');

        Log::info('Updated order from payment intent succeeded', [
            'order_id' => $order->id,
            'payment_intent_id' => $paymentIntent->id,
        ]);
    }

    /**
     * Handle payment intent failed webhook
     */
    protected function handlePaymentIntentFailed(object $paymentIntent): void
    {
        $order = Order::where('stripe_payment_intent_id', $paymentIntent->id)->first();

        if (!$order) {
            Log::warning('Order not found for failed payment intent', [
                'payment_intent_id' => $paymentIntent->id,
            ]);
            return;
        }

        $order->updatePaymentStatus('failed');

        Log::info('Updated order from payment intent failed', [
            'order_id' => $order->id,
            'payment_intent_id' => $paymentIntent->id,
        ]);
    }

    /**
     * Handle checkout session expired webhook
     */
    protected function handleCheckoutSessionExpired(object $session): void
    {
        $order = Order::where('stripe_checkout_session_id', $session->id)->first();

        if (!$order) {
            Log::warning('Order not found for expired checkout session', [
                'session_id' => $session->id,
            ]);
            return;
        }

        $order->updatePaymentStatus('cancelled');

        Log::info('Updated order from checkout session expired', [
            'order_id' => $order->id,
            'session_id' => $session->id,
        ]);
    }
}
