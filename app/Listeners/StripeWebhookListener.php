<?php

namespace App\Listeners;

use Laravel\Cashier\Events\WebhookReceived;
use App\Models\Order;
use App\Models\Cart;
use App\Models\Address;
use App\Services\EmailService;
use Illuminate\Support\Facades\Log;

class StripeWebhookListener
{
    /**
     * Handle the event.
     */
    public function handle(WebhookReceived $event): void
    {
        $payload = $event->payload;
        
        Log::info('Processing Stripe webhook event', [
            'type' => $payload['type'],
            'id' => $payload['id']
        ]);

        switch ($payload['type']) {
            case 'checkout.session.completed':
                $this->handleCheckoutSessionCompleted($payload);
                break;
            case 'payment_intent.succeeded':
                $this->handlePaymentIntentSucceeded($payload);
                break;
            default:
                Log::info('Unhandled webhook event type: ' . $payload['type']);
                break;
        }
    }

    /**
     * Handle a successful checkout session completion.
     */
    private function handleCheckoutSessionCompleted(array $payload): void
    {
        Log::info('Processing checkout.session.completed webhook', ['session_id' => $payload['data']['object']['id']]);
        
        $session = $payload['data']['object'];
        
        // Only process if payment was successful
        if ($session['payment_status'] === 'paid') {
            $this->createOrderFromSession($session['id'], null, $session);
        }
    }

    /**
     * Handle a successful payment intent.
     */
    private function handlePaymentIntentSucceeded(array $payload): void
    {
        Log::info('Processing payment_intent.succeeded webhook', ['payment_intent_id' => $payload['data']['object']['id']]);
        
        $paymentIntent = $payload['data']['object'];
        $sessionId = $paymentIntent['metadata']['checkout_session_id'] ?? null;
        
        if ($sessionId) {
            $this->createOrderFromSession($sessionId, $paymentIntent);
        } else {
            Log::warning('No checkout session ID found in payment intent metadata', [
                'payment_intent_id' => $paymentIntent['id']
            ]);
        }
    }

    /**
     * Create an order from a Stripe checkout session
     */
    private function createOrderFromSession(string $sessionId, ?array $paymentIntent = null, ?array $session = null): void
    {
        try {
            // Check if order already exists
            $existingOrder = Order::where('stripe_checkout_session_id', $sessionId)->first();
            if ($existingOrder) {
                Log::info('Order already exists for session', ['session_id' => $sessionId, 'order_id' => $existingOrder->id]);
                
                // Send email if not sent yet
                if (!$existingOrder->hasEmailBeenSent()) {
                    $emailService = app(EmailService::class);
                    $emailService->sendOrderConfirmation($existingOrder);
                }
                return;
            }

            // Retrieve session from Stripe if not provided
            if (!$session) {
                $stripe = new \Stripe\StripeClient(env('STRIPE_SECRET'));
                $session = $stripe->checkout->sessions->retrieve($sessionId);
            }

            // Extract metadata
            $metadata = $session['metadata'] ?? [];
            $isGuestOrder = !isset($metadata['user_id']) || empty($metadata['user_id']);
            $cartId = $metadata['cart_id'] ?? null;
            $guestSessionId = $metadata['guest_session_id'] ?? null;

            // Find the cart
            $cart = null;
            if ($cartId) {
                $cart = Cart::with(['cartItems.product', 'cartItems.size'])->find($cartId);
            } elseif ($guestSessionId) {
                $cart = Cart::where('session_id', $guestSessionId)
                    ->with(['cartItems.product', 'cartItems.size'])
                    ->first();
            }

            if (!$cart || $cart->cartItems->isEmpty()) {
                Log::error('No cart found for session', [
                    'session_id' => $sessionId,
                    'cart_id' => $cartId,
                    'guest_session_id' => $guestSessionId
                ]);
                return;
            }

            // Parse shipping address from metadata
            $shippingAddressString = $metadata['shipping_address'] ?? '';
            $addressParts = $this->parseShippingAddress($shippingAddressString);

            // Create addresses
            $billingAddress = $this->createAddressFromSession($session, $addressParts, 'billing');
            $shippingAddress = $this->createAddressFromSession($session, $addressParts, 'shipping');

            // Calculate totals
            $subtotal = $cart->cartItems->sum(function ($item) {
                return $item->price * $item->quantity;
            });
            
            // Use cart's size-based shipping cost, or fall back to Stripe session amount
            $shippingCost = $cart ? $cart->shipping_cost : (($session['total_details']['amount_shipping'] ?? 0) / 100);
            $taxAmount = ($session['total_details']['amount_tax'] ?? 0) / 100;
            $totalAmount = $session['amount_total'] / 100;

            // Create order
            $orderData = [
                'order_number' => Order::generateOrderNumber(),
                'user_id' => $isGuestOrder ? null : $metadata['user_id'],
                'billing_address_id' => $billingAddress->id,
                'shipping_address_id' => $shippingAddress->id,
                'status' => 'processing',
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'shipping_amount' => $shippingCost,
                'total_amount' => $totalAmount,
                'currency' => strtoupper($session['currency']),
                'notes' => $metadata['order_notes'] ?? null,
                'stripe_checkout_session_id' => $sessionId,
                'stripe_payment_intent_id' => $paymentIntent['id'] ?? $session['payment_intent'] ?? null,
                'payment_status' => 'succeeded',
                'payment_method' => 'stripe',
            ];

            // Add guest-specific fields
            if ($isGuestOrder) {
                $orderData['guest_email'] = $session['customer_details']['email'] ?? $metadata['guest_email'];
                $orderData['guest_phone'] = $session['customer_details']['phone'] ?? null;
                $orderData['guest_session_id'] = $guestSessionId;
            }

            $order = Order::create($orderData);

            // Create order items
            foreach ($cart->cartItems as $cartItem) {
                $order->orderItems()->create([
                    'product_id' => $cartItem->product_id,
                    'product_name' => $cartItem->product->name,
                    'quantity' => $cartItem->quantity,
                    'price' => $cartItem->price,
                    'total' => $cartItem->price * $cartItem->quantity,
                ]);
            }

            Log::info('Order created from webhook', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'session_id' => $sessionId,
                'is_guest' => $isGuestOrder,
                'total_amount' => $order->total_amount
            ]);

            // Send order confirmation email
            $emailService = app(EmailService::class);
            $emailSent = $emailService->sendOrderConfirmation($order);
            
            if ($emailSent) {
                Log::info('Order confirmation email sent from webhook', ['order_id' => $order->id]);
            } else {
                Log::warning('Failed to send order confirmation email from webhook', ['order_id' => $order->id]);
            }

            // Clear the cart after successful order creation
            $cart->cartItems()->delete();
            $cart->delete();

        } catch (\Exception $e) {
            Log::error('Failed to create order from webhook', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
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
    private function createAddressFromSession(array $session, array $addressParts, string $type): Address
    {
        $customerDetails = $session['customer_details'] ?? [];
        $address = $customerDetails['address'] ?? [];
        
        // Parse name
        $nameParts = explode(' ', $addressParts['name'] ?: ($customerDetails['name'] ?? ''), 2);
        $firstName = $nameParts[0] ?? '';
        $lastName = $nameParts[1] ?? '';
        
        // Parse state and postal code
        $statePostal = $addressParts['state_postal'] ?? '';
        $statePostalParts = explode(' ', $statePostal);
        $state = $statePostalParts[0] ?? '';
        $postalCode = $statePostalParts[1] ?? '';

        return Address::create([
            'user_id' => null, // Guest address
            'type' => $type,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'company' => null,
            'address_line_1' => $addressParts['address_line_1'] ?: ($address['line1'] ?? ''),
            'address_line_2' => $address['line2'] ?? null,
            'city' => $addressParts['city'] ?: ($address['city'] ?? ''),
            'state' => $state ?: ($address['state'] ?? ''),
            'postal_code' => $postalCode ?: ($address['postal_code'] ?? ''),
            'country' => $addressParts['country'] ?: ($address['country'] ?? 'US'),
            'phone' => $customerDetails['phone'] ?? null,
        ]);
    }

    /**
     * Calculate shipping cost
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
}
