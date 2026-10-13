<?php

namespace App\Services;

use App\Models\Address;
use App\Models\Cart;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Stripe\StripeObject;
use Throwable;

/**
 * Single, idempotent place where a paid Stripe checkout session becomes an Order.
 *
 * Called by the Stripe webhook listener and by the checkout success page, in any order and any
 * number of times: exactly one order exists per Stripe checkout session.
 */
class OrderCreationService
{
    public const SUPPORTED_LOCALES = ['it', 'en', 'de', 'es', 'fr'];

    public const FALLBACK_LOCALE = 'it';

    /**
     * Value of metadata.source on every checkout session this shop creates.
     */
    public const SESSION_SOURCE = 'rsp-ecom';

    /**
     * Find or create the order for a Stripe checkout session.
     *
     * Returns null (and creates nothing) for a session that does not belong to this shop.
     *
     * @param  array|object  $session  Stripe checkout session (webhook payload array or Stripe object)
     * @param  array|object|null  $paymentIntent  Stripe payment intent, when the caller has one
     */
    public function createFromStripeSession(array|object $session, array|object|null $paymentIntent = null): ?Order
    {
        $session = $this->toArray($session);
        $paymentIntent = $paymentIntent === null ? null : $this->toArray($paymentIntent);

        $sessionId = $session['id'] ?? null;
        if (! $sessionId) {
            throw new \InvalidArgumentException('Stripe checkout session without an id');
        }

        $order = $this->findOrCreate($sessionId, $session, $paymentIntent);

        if (! $order) {
            return null;
        }

        // Outside the transaction: the order is committed. EmailService is idempotent, so calling
        // it again for an already known order is safe and lets a lost confirmation be recovered.
        if ($order->isPaymentCompleted()) {
            $this->requestConfirmationEmail($order);

            // Only on the first payment confirmation (new order, or an unpaid one that just became paid):
            // replays of the webhook / success page find the order unchanged and alert nobody.
            if ($order->wasRecentlyCreated || $order->wasChanged('payment_status')) {
                $this->requestNewOrderAlert($order);
            }
        }

        return $order;
    }

    /**
     * Retrieve a checkout session from Stripe as a plain array.
     */
    public function retrieveSession(string $sessionId): array
    {
        $stripe = StripeService::getClient();

        if (! $stripe) {
            throw new RuntimeException('Stripe secret key not configured');
        }

        return $this->toArray($stripe->checkout->sessions->retrieve($sessionId));
    }

    protected function findOrCreate(string $sessionId, array $session, ?array $paymentIntent): ?Order
    {
        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                return DB::transaction(
                    fn () => $this->findOrCreateInTransaction($sessionId, $session, $paymentIntent),
                    3 // retried on deadlocks only
                );
            } catch (UniqueConstraintViolationException $e) {
                // Lost a race against a concurrent creation for the same session (unique index on
                // stripe_checkout_session_id): use the winner's order.
                $existing = Order::where('stripe_checkout_session_id', $sessionId)->first();
                if ($existing) {
                    Log::info('Order already created concurrently for session', [
                        'session_id' => $sessionId,
                        'order_id' => $existing->id,
                    ]);

                    return $existing;
                }

                // Some other unique collision (e.g. random order number): try again a few times.
                if ($attempt >= 3) {
                    throw $e;
                }
            }
        }
    }

    protected function findOrCreateInTransaction(string $sessionId, array $session, ?array $paymentIntent): ?Order
    {
        $order = $this->lockedLookup($sessionId);

        if ($order) {
            Log::info('Order already exists for session', ['session_id' => $sessionId, 'order_id' => $order->id]);

            return $this->syncPaymentState($order, $session, $paymentIntent);
        }

        if (! $this->belongsToShop($session)) {
            Log::warning('Ignoring Stripe checkout session that does not belong to this shop', [
                'session_id' => $sessionId,
            ]);

            return null;
        }

        return $this->createOrder($sessionId, $session, $paymentIntent);
    }

    /**
     * A session is ours when it carries the shop marker or one of the keys the checkout code
     * has always put in the metadata (older sessions have no marker).
     */
    protected function belongsToShop(array $session): bool
    {
        $metadata = $this->metadata($session);

        if (($metadata['source'] ?? null) === self::SESSION_SOURCE) {
            return true;
        }

        foreach (['cart_id', 'guest_session_id', 'user_id'] as $key) {
            if (! empty($metadata[$key])) {
                return true;
            }
        }

        return false;
    }

    protected function lockedLookup(string $sessionId): ?Order
    {
        return Order::where('stripe_checkout_session_id', $sessionId)->lockForUpdate()->first();
    }

    /**
     * An order created from a not-yet-paid session becomes paid when a later event confirms it.
     */
    protected function syncPaymentState(Order $order, array $session, ?array $paymentIntent): Order
    {
        $updates = [];
        $paymentIntentId = $paymentIntent['id'] ?? $this->stringOrNull($session['payment_intent'] ?? null);

        if (empty($order->stripe_payment_intent_id) && $paymentIntentId) {
            $updates['stripe_payment_intent_id'] = $paymentIntentId;
        }

        $becamePaid = $this->isPaid($session, $paymentIntent) && $order->isPaymentPending();
        if ($becamePaid) {
            $updates['payment_status'] = 'succeeded';
            if ($order->status === 'pending') {
                $updates['status'] = 'processing';
            }
        }

        if ($updates) {
            $order->update($updates);
        }

        if ($becamePaid) {
            Log::info('Order payment status updated to succeeded', ['order_id' => $order->id]);
            $this->clearCart($this->trustedCart($this->metadata($session), $session['id'] ?? null));
        }

        return $order;
    }

    protected function createOrder(string $sessionId, array $session, ?array $paymentIntent): Order
    {
        $metadata = $this->metadata($session);
        $paid = $this->isPaid($session, $paymentIntent);

        $userId = $this->resolveUserId($metadata);
        $isGuestOrder = $userId === null;

        $cart = $this->trustedCart($metadata, $sessionId);
        $cartItems = $cart ? $cart->cartItems : collect();

        $addressParts = $this->parseShippingAddress($metadata['shipping_address'] ?? '');
        $billingAddress = $this->createAddress($session, $addressParts, 'billing');
        $shippingAddress = $this->createAddress($session, $addressParts, 'shipping');

        $totals = $this->calculateTotals($session, $cart);

        $couponCode = trim((string) ($metadata['coupon_code'] ?? ''));
        $phone = ($metadata['customer_phone'] ?? null) ?: ($session['customer_details']['phone'] ?? null);

        $orderData = [
            'order_number' => Order::generateOrderNumber(),
            'user_id' => $userId,
            'billing_address_id' => $billingAddress->id,
            'shipping_address_id' => $shippingAddress->id,
            'status' => $paid ? 'processing' : 'pending',
            'subtotal' => $totals['subtotal'],
            'tax_amount' => $totals['tax'],
            'shipping_amount' => $totals['shipping'],
            'discount_amount' => $totals['discount'],
            'coupon_code' => $couponCode !== '' ? $couponCode : null,
            'total_amount' => $totals['total'],
            'currency' => strtoupper($session['currency'] ?? 'eur'),
            'locale' => $this->resolveLocale($metadata['locale'] ?? null),
            'notes' => ($metadata['order_notes'] ?? '') !== '' ? $metadata['order_notes'] : null,
            'stripe_checkout_session_id' => $sessionId,
            'stripe_payment_intent_id' => $paymentIntent['id'] ?? $this->stringOrNull($session['payment_intent'] ?? null),
            'payment_status' => $paid ? 'succeeded' : 'processing',
            'payment_method' => 'stripe',
        ];

        if ($isGuestOrder) {
            $orderData['guest_email'] = $session['customer_details']['email'] ?? ($metadata['guest_email'] ?? null);
            $orderData['guest_phone'] = $phone;
            $orderData['guest_session_id'] = $metadata['guest_session_id'] ?? null;
        }

        $order = Order::create($orderData);

        if ($cartItems->isNotEmpty()) {
            foreach ($cartItems as $cartItem) {
                $order->orderItems()->create([
                    'product_id' => $cartItem->product_id,
                    'product_variant_id' => $cartItem->product_variant_id,
                    'size_id' => $cartItem->size_id,
                    'product_name' => $cartItem->product?->name ?? 'Unknown Product',
                    'quantity' => $cartItem->quantity,
                    'price' => $cartItem->price,
                    'total' => $cartItem->price * $cartItem->quantity,
                ]);
            }
        } else {
            Log::error('Order created without a usable cart: items are taken from the Stripe session', [
                'session_id' => $sessionId,
                'order_id' => $order->id,
                'cart_id' => $metadata['cart_id'] ?? null,
                'guest_session_id' => $metadata['guest_session_id'] ?? null,
            ]);

            $this->createOrderItemsFromSession($order, $session);
        }

        // Only a paid session consumes the cart; an unpaid one keeps it until payment is confirmed.
        if ($paid) {
            $this->clearCart($cart);
        }

        Log::info('Order created from Stripe session', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'session_id' => $sessionId,
            'is_guest' => $isGuestOrder,
            'total_amount' => $order->total_amount,
        ]);

        return $order;
    }

    /**
     * Totals as charged by Stripe: the paid total is authoritative. The discount is Stripe's own
     * discount when present, otherwise whatever the cart (items + shipping) exceeds the paid total by.
     *
     * @return array{subtotal: float, shipping: float, tax: float, discount: float, total: float}
     */
    protected function calculateTotals(array $session, ?Cart $cart): array
    {
        $details = $session['total_details'] ?? [];

        $total = round(($session['amount_total'] ?? 0) / 100, 2);
        $tax = round(($details['amount_tax'] ?? 0) / 100, 2);
        $stripeDiscount = round(($details['amount_discount'] ?? 0) / 100, 2);

        if ($cart && $cart->cartItems->isNotEmpty()) {
            $subtotal = round($cart->cartItems->sum(fn ($item) => $item->price * $item->quantity), 2);
            $shipping = round($cart->shipping_cost, 2);
        } else {
            $subtotal = round((($session['amount_subtotal'] ?? $session['amount_total'] ?? 0)) / 100, 2);
            $shipping = round(($details['amount_shipping'] ?? 0) / 100, 2);
        }

        // A difference of a cent or so is rounding noise (sessions created before amounts were rounded
        // instead of truncated), not a discount: never show the customer a phantom "Discount: -0.01".
        $derivedDiscount = max(0, round($subtotal + $shipping - $total, 2));
        $discount = $stripeDiscount > 0
            ? $stripeDiscount
            : ($derivedDiscount >= 0.02 ? $derivedDiscount : 0.0);

        if ($stripeDiscount <= 0 && $subtotal + $shipping < $total) {
            Log::warning('Paid total is higher than the cart total; the cart may have changed after checkout', [
                'session_id' => $session['id'] ?? null,
                'cart_total' => $subtotal + $shipping,
                'paid_total' => $total,
            ]);
        }

        return [
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'tax' => $tax,
            'discount' => $discount,
            'total' => $total,
        ];
    }

    protected function findCart(array $metadata): ?Cart
    {
        $with = ['cartItems.product', 'cartItems.size'];
        $cart = null;

        if (! empty($metadata['cart_id'])) {
            $cart = Cart::with($with)->find($metadata['cart_id']);
        }

        if (! $cart && ! empty($metadata['guest_session_id'])) {
            $cart = Cart::where('session_id', $metadata['guest_session_id'])->with($with)->first();
        }

        return $cart;
    }

    /**
     * The cart of a session, unless it was changed after the checkout started (its content hash no
     * longer matches the one stored in the session metadata): then it is not trusted and not touched.
     * Sessions without a hash (created before it existed) keep the previous behaviour.
     */
    protected function trustedCart(array $metadata, ?string $sessionId): ?Cart
    {
        $cart = $this->findCart($metadata);
        $expectedHash = $metadata['cart_hash'] ?? null;

        if (! $cart || empty($expectedHash)) {
            return $cart;
        }

        $currentHash = $cart->contentHash();
        if (! hash_equals((string) $expectedHash, $currentHash)) {
            Log::error('Cart changed after checkout started; ignoring it and using the Stripe line items', [
                'session_id' => $sessionId,
                'cart_id' => $cart->id,
                'expected_cart_hash' => $expectedHash,
                'current_cart_hash' => $currentHash,
            ]);

            return null;
        }

        return $cart;
    }

    protected function clearCart(?Cart $cart): void
    {
        if ($cart) {
            $cart->cartItems()->delete();
            $cart->delete();
        }
    }

    /**
     * Registered buyer from the session metadata; unknown/deleted users degrade to a guest order.
     */
    protected function resolveUserId(array $metadata): ?int
    {
        if (empty($metadata['user_id'])) {
            return null;
        }

        $user = User::find($metadata['user_id']);
        if (! $user) {
            Log::warning('Checkout session references an unknown user; creating a guest order', [
                'user_id' => $metadata['user_id'],
            ]);
        }

        return $user?->id;
    }

    protected function resolveLocale(mixed $locale): string
    {
        if (! is_string($locale)) {
            return self::FALLBACK_LOCALE;
        }

        $locale = strtolower(explode('-', str_replace('_', '-', trim($locale)))[0]);

        return in_array($locale, self::SUPPORTED_LOCALES, true) ? $locale : self::FALLBACK_LOCALE;
    }

    protected function isPaid(array $session, ?array $paymentIntent): bool
    {
        return ($session['payment_status'] ?? null) === 'paid' || $paymentIntent !== null;
    }

    /**
     * Fallback when the cart is gone: line items from Stripe, or one item describing the purchase.
     */
    protected function createOrderItemsFromSession(Order $order, array $session): void
    {
        $lineItems = $this->fetchLineItems($session['id']);

        // The live checkouts charge one lump-sum "Cart Purchase" line; the metadata describes the cart.
        $summary = count($lineItems) === 1 ? ($session['metadata']['description'] ?? null) : null;

        foreach ($lineItems as $item) {
            $unitAmount = $item['price']['unit_amount'] ?? $item['amount_subtotal'] ?? 0;
            $quantity = $item['quantity'] ?? 1;

            $order->orderItems()->create([
                'product_id' => null,
                'product_name' => mb_substr((string) ($summary ?: ($item['description'] ?? 'Cart Purchase')), 0, 255),
                'quantity' => $quantity,
                'price' => $unitAmount / 100,
                'total' => ($unitAmount * $quantity) / 100,
            ]);
        }

        if (! $lineItems) {
            $description = $session['metadata']['description'] ?? null;

            $order->orderItems()->create([
                'product_id' => null,
                'product_name' => mb_substr($description ?: 'Cart Purchase', 0, 255),
                'quantity' => 1,
                'price' => $order->total_amount,
                'total' => $order->total_amount,
            ]);
        }
    }

    /**
     * @return array<int, array>
     */
    protected function fetchLineItems(string $sessionId): array
    {
        try {
            $stripe = StripeService::getClient();
            if (! $stripe) {
                return [];
            }

            return $this->toArray($stripe->checkout->sessions->allLineItems($sessionId))['data'] ?? [];
        } catch (Throwable $e) {
            Log::error('Failed to fetch Stripe line items for order items', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Parse shipping address from the metadata string
     * Expected format: "John Doe, 123 Main St, City, State ZIP, Country"
     */
    protected function parseShippingAddress(string $addressString): array
    {
        $parts = array_map('trim', explode(', ', $addressString));

        return [
            'name' => $parts[0] ?? '',
            'address_line_1' => $parts[1] ?? '',
            'city' => $parts[2] ?? '',
            'state_postal' => $parts[3] ?? '',
            'country' => $parts[4] ?? '',
        ];
    }

    /**
     * Create an address record; metadata values win, Stripe's customer details are the fallback.
     */
    protected function createAddress(array $session, array $addressParts, string $type): Address
    {
        $customerDetails = $session['customer_details'] ?? [];
        $address = is_array($customerDetails['address'] ?? null) ? $customerDetails['address'] : [];

        $nameParts = explode(' ', $addressParts['name'] ?: ($customerDetails['name'] ?? ''), 2);
        $firstName = $nameParts[0] ?? '';
        $lastName = $nameParts[1] ?? '';

        $statePostalParts = explode(' ', $addressParts['state_postal'] ?? '');
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
            'country' => $addressParts['country'] ?: ($address['country'] ?? 'IT'),
            'phone' => ($session['metadata']['customer_phone'] ?? null) ?: ($customerDetails['phone'] ?? null),
        ]);
    }

    protected function requestConfirmationEmail(Order $order): void
    {
        try {
            $accepted = app(EmailService::class)->sendOrderConfirmation($order);

            if (! $accepted) {
                Log::warning('Order confirmation email was not accepted for delivery', ['order_id' => $order->id]);
            }
        } catch (Throwable $e) {
            // The order is already committed; a mail problem must not fail the payment flow.
            Log::error('Order confirmation email could not be requested', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function requestNewOrderAlert(Order $order): void
    {
        try {
            app(EmailService::class)->sendNewOrderAlert($order);
        } catch (Throwable $e) {
            // Internal courtesy mail: never fail order creation because of it.
            Log::error('New order alert could not be requested', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function metadata(array $session): array
    {
        return is_array($session['metadata'] ?? null) ? $session['metadata'] : [];
    }

    protected function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Normalise Stripe objects / stdClass / arrays into plain nested arrays.
     */
    protected function toArray(array|object $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if ($value instanceof StripeObject) {
            return $value->toArray();
        }

        return json_decode(json_encode($value), true) ?? [];
    }
}
