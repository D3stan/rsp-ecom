<?php

namespace App\Services;

use App\Models\Address;
use App\Models\Cart;
use App\Models\CheckoutSnapshot;
use App\Models\Order;
use App\Models\User;
use App\Support\Locales;
use App\Support\Vat;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
    /**
     * @deprecated Alias kept for existing callers; the source of truth is config('app.supported_locales')
     *             (App\Support\Locales). A test pins both lists to the same set.
     */
    public const SUPPORTED_LOCALES = ['en', 'es', 'fr', 'de', 'it'];

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
     * The order is built from the checkout snapshot named in metadata.snapshot_id (what the customer was charged
     * for), falling back to the live cart and then to the Stripe line items for sessions without one.
     *
     * @param  array|object  $session  Stripe checkout session (webhook payload array or Stripe object)
     */
    public function createFromStripeSession(array|object $session): ?Order
    {
        $session = $this->toArray($session);

        $sessionId = $session['id'] ?? null;
        if (! $sessionId) {
            throw new \InvalidArgumentException('Stripe checkout session without an id');
        }

        $order = $this->findOrCreate($sessionId, $session);

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

            // The shop sold units it did not have: tell the owner (once per order, EmailService dedupes)
            if ($order->has_oversold_items) {
                $this->requestOversoldAlert($order);
            }
        }

        return $order;
    }

    /**
     * Close the order of a checkout that did not end in a payment (checkout.session.async_payment_failed,
     * checkout.session.expired). Only an order whose payment is still pending is touched: a paid order is never
     * downgraded. The customer is not emailed (the OrderObserver stays silent for an order that was never confirmed),
     * the cart stays as it is, and the checkout snapshot is marked as used up.
     *
     * @param  array|object  $session  Stripe checkout session
     * @param  string  $outcome  'failed' (async payment failed) or 'expired' (the customer never paid)
     */
    public function closeUnpaidSession(array|object $session, string $outcome): ?Order
    {
        $session = $this->toArray($session);
        $sessionId = $session['id'] ?? null;

        if (! $sessionId) {
            throw new \InvalidArgumentException('Stripe checkout session without an id');
        }

        if (! $this->belongsToShop($session)) {
            Log::warning('Ignoring Stripe checkout session that does not belong to this shop', ['session_id' => $sessionId]);

            return null;
        }

        return DB::transaction(function () use ($sessionId, $session, $outcome) {
            $this->retireSnapshot($this->metadata($session), $sessionId);

            $order = $this->lockedLookup($sessionId);

            if (! $order) {
                return null;
            }

            if (! $order->isPaymentPending()) {
                Log::info('Not closing an order whose payment is not pending', [
                    'order_id' => $order->id,
                    'payment_status' => $order->payment_status,
                    'outcome' => $outcome,
                ]);

                return $order;
            }

            $order->payment_status = $outcome === 'expired' ? 'cancelled' : 'failed';

            if ($order->can_be_cancelled) {
                $order->transitionTo('cancelled', null, null, ['source' => 'stripe', 'outcome' => $outcome]);
            } else {
                $order->save();
            }

            Log::info('Order closed: its checkout did not end in a payment', ['order_id' => $order->id, 'outcome' => $outcome]);

            return $order;
        });
    }

    protected function findOrCreate(string $sessionId, array $session): ?Order
    {
        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                return DB::transaction(
                    fn () => $this->findOrCreateInTransaction($sessionId, $session),
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

    protected function findOrCreateInTransaction(string $sessionId, array $session): ?Order
    {
        $order = $this->lockedLookup($sessionId);

        if ($order) {
            Log::info('Order already exists for session', ['session_id' => $sessionId, 'order_id' => $order->id]);

            return $this->syncPaymentState($order, $session);
        }

        if (! $this->belongsToShop($session)) {
            Log::warning('Ignoring Stripe checkout session that does not belong to this shop', [
                'session_id' => $sessionId,
            ]);

            return null;
        }

        return $this->createOrder($sessionId, $session);
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
    protected function syncPaymentState(Order $order, array $session): Order
    {
        $updates = [];
        $paymentIntentId = $this->stringOrNull($session['payment_intent'] ?? null);

        if (empty($order->stripe_payment_intent_id) && $paymentIntentId) {
            $updates['stripe_payment_intent_id'] = $paymentIntentId;
        }

        $becamePaid = $this->isPaid($session) && $order->isPaymentPending();
        if ($becamePaid) {
            $updates['payment_status'] = 'succeeded';
        }

        if ($becamePaid && $order->status === 'pending') {
            $order->fill($updates)->transitionTo('processing', null, null, ['source' => 'stripe']);
        } elseif ($updates) {
            $order->update($updates);
        }

        if ($becamePaid) {
            Log::info('Order payment status updated to succeeded', ['order_id' => $order->id]);
            $this->clearCartAfterPayment($this->metadata($session), $session['id'] ?? null);
            app(StockService::class)->deductForOrder($order);
        }

        return $order;
    }

    protected function createOrder(string $sessionId, array $session): Order
    {
        $metadata = $this->metadata($session);
        $paid = $this->isPaid($session);

        $userId = $this->resolveUserId($metadata);
        $isGuestOrder = $userId === null;

        // What the customer was charged for, frozen before the Stripe session existed; else the live cart
        $snapshot = $this->consumeSnapshot($metadata, $sessionId);
        $cart = $snapshot ? null : $this->trustedCart($metadata, $sessionId);
        $cartItems = $cart ? $cart->cartItems : collect();

        $addressParts = $this->parseShippingAddress($metadata['shipping_address'] ?? '');
        $billingAddress = $this->createAddress($session, $addressParts, 'billing');
        $shippingAddress = $this->createAddress($session, $addressParts, 'shipping');

        $totals = $snapshot ? $this->totalsFromSnapshot($snapshot, $session) : $this->calculateTotals($session, $cart);

        $couponCode = trim((string) ($snapshot?->coupon_code ?? $metadata['coupon_code'] ?? ''));
        $phone = ($metadata['customer_phone'] ?? null) ?: ($session['customer_details']['phone'] ?? null);

        $orderData = [
            'order_number' => Order::generateOrderNumber(),
            'user_id' => $userId,
            'billing_address_id' => $billingAddress->id,
            'shipping_address_id' => $shippingAddress->id,
            'status' => $paid ? 'processing' : 'pending',
            'subtotal' => $totals['subtotal'],
            'tax_amount' => $totals['tax'],
            'tax_rate' => $totals['tax_rate'],
            'shipping_amount' => $totals['shipping'],
            'discount_amount' => $totals['discount'],
            'coupon_code' => $couponCode !== '' ? $couponCode : null,
            'total_amount' => $totals['total'],
            'currency' => strtoupper($session['currency'] ?? 'eur'),
            'locale' => $this->resolveLocale($metadata['locale'] ?? null),
            'notes' => ($metadata['order_notes'] ?? '') !== '' ? $metadata['order_notes'] : null,
            'stripe_checkout_session_id' => $sessionId,
            'stripe_payment_intent_id' => $this->stringOrNull($session['payment_intent'] ?? null),
            'payment_status' => $paid ? 'succeeded' : 'processing',
            'payment_method' => 'stripe',
        ];

        if ($isGuestOrder) {
            $orderData['guest_email'] = $session['customer_details']['email'] ?? ($metadata['guest_email'] ?? null);
            $orderData['guest_phone'] = $phone;
            $orderData['guest_session_id'] = $metadata['guest_session_id'] ?? null;
        }

        $order = Order::create($orderData);

        if ($snapshot) {
            foreach ($snapshot->lines as $line) {
                $order->orderItems()->create([
                    'product_id' => $line['product_id'] ?? null,
                    'product_variant_id' => $line['product_variant_id'] ?? null,
                    'size_id' => $line['size_id'] ?? null,
                    'product_name' => mb_substr((string) ($line['product_name'] ?? 'Unknown Product'), 0, 255),
                    'quantity' => (int) $line['quantity'],
                    'price' => $line['price'],
                    'total' => round($line['price'] * (int) $line['quantity'], 2),
                ]);
            }
        } elseif ($cartItems->isNotEmpty()) {
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

        // Only a paid session consumes the cart and the stock; an unpaid one keeps both until payment is confirmed.
        if ($paid) {
            if ($snapshot) {
                $this->clearCartAfterPayment($metadata, $sessionId, $snapshot);
            } else {
                $this->clearCart($cart);
            }

            app(StockService::class)->deductForOrder($order);
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
     * VAT is computed here, once: the portion contained in the paid total (which already reflects any discount and
     * includes shipping) at the current `tax.rate`, stored together with that rate. Stripe's own tax figure is not
     * used: the checkout charges one VAT-inclusive amount and Stripe calculates no tax on it.
     *
     * @return array{subtotal: float, shipping: float, tax: float, tax_rate: float|null, discount: float, total: float}
     */
    protected function calculateTotals(array $session, ?Cart $cart): array
    {
        $details = $session['total_details'] ?? [];

        $total = round(($session['amount_total'] ?? 0) / 100, 2);
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

        $vat = Vat::includedIn($total);

        return [
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'tax' => $vat['amount'],
            'tax_rate' => $vat['rate'],
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

    /**
     * The snapshot named by metadata.snapshot_id, when it may be used for this session: it exists, and it is either
     * unbound / bound to this very session. A snapshot of another session is never used (logged, fallback chain).
     */
    protected function resolveSnapshot(array $metadata, string $sessionId): ?CheckoutSnapshot
    {
        $id = $metadata['snapshot_id'] ?? null;

        if (empty($id) || ! is_string($id)) {
            return null;
        }

        $snapshot = CheckoutSnapshot::query()->lockForUpdate()->find($id);

        if (! $snapshot) {
            Log::warning('Checkout snapshot not found; falling back to the cart', ['session_id' => $sessionId, 'snapshot_id' => $id]);

            return null;
        }

        $boundElsewhere = $snapshot->stripe_session_id !== null && $snapshot->stripe_session_id !== $sessionId;

        if ($boundElsewhere || ($snapshot->isConsumed() && $snapshot->stripe_session_id !== $sessionId)) {
            Log::error('Checkout snapshot belongs to another session; falling back to the cart', [
                'session_id' => $sessionId,
                'snapshot_id' => $id,
            ]);

            return null;
        }

        return $snapshot;
    }

    /**
     * Use the snapshot for a new order: bind it to the session and mark it consumed (same transaction as the order).
     */
    protected function consumeSnapshot(array $metadata, string $sessionId): ?CheckoutSnapshot
    {
        $snapshot = $this->resolveSnapshot($metadata, $sessionId);

        if (! $snapshot) {
            return null;
        }

        $snapshot->forceFill([
            'stripe_session_id' => $sessionId,
            'consumed_at' => $snapshot->consumed_at ?? now(),
        ])->save();

        return $snapshot;
    }

    /**
     * A checkout that ended without a payment: its snapshot is used up (bound to the session when it was not yet).
     */
    protected function retireSnapshot(array $metadata, string $sessionId): void
    {
        $snapshot = CheckoutSnapshot::query()
            ->where(function ($query) use ($metadata, $sessionId) {
                $query->where('stripe_session_id', $sessionId);

                if (! empty($metadata['snapshot_id']) && is_string($metadata['snapshot_id'])) {
                    $query->orWhere('id', $metadata['snapshot_id']);
                }
            })
            ->whereNull('consumed_at')
            ->lockForUpdate()
            ->first();

        if ($snapshot && ($snapshot->stripe_session_id === null || $snapshot->stripe_session_id === $sessionId)) {
            $snapshot->forceFill([
                'stripe_session_id' => $sessionId,
                'consumed_at' => now(),
            ])->save();
        }
    }

    /**
     * Totals exactly as charged (the snapshot); VAT is the portion contained in that total.
     *
     * @return array{subtotal: float, shipping: float, tax: float, tax_rate: float|null, discount: float, total: float}
     */
    protected function totalsFromSnapshot(CheckoutSnapshot $snapshot, array $session): array
    {
        if (isset($session['amount_total']) && (int) $session['amount_total'] !== (int) $snapshot->total_cents) {
            Log::error('Paid amount differs from the checkout snapshot', [
                'session_id' => $session['id'] ?? null,
                'snapshot_id' => $snapshot->id,
                'paid_cents' => (int) $session['amount_total'],
                'snapshot_cents' => (int) $snapshot->total_cents,
            ]);
        }

        $total = round((float) $snapshot->total, 2);
        $vat = Vat::includedIn($total);

        return [
            'subtotal' => round((float) $snapshot->subtotal, 2),
            'shipping' => round((float) $snapshot->shipping, 2),
            'tax' => $vat['amount'],
            'tax_rate' => $vat['rate'],
            'discount' => round((float) $snapshot->discount, 2),
            'total' => $total,
        ];
    }

    /**
     * Remove the cart a paid checkout was started from, unless the customer changed it since: with a snapshot the cart
     * is only cleared when its content hash still equals the snapshot's (an edited cart is left untouched), without one
     * the hash stored in the session metadata decides (trustedCart).
     */
    protected function clearCartAfterPayment(array $metadata, ?string $sessionId, ?CheckoutSnapshot $snapshot = null): void
    {
        $snapshot ??= $sessionId ? $this->resolveSnapshot($metadata, $sessionId) : null;

        if (! $snapshot) {
            $this->clearCart($this->trustedCart($metadata, $sessionId));

            return;
        }

        $cart = $this->findCart($metadata);

        if (! $cart) {
            return;
        }

        if (hash_equals((string) $snapshot->cart_hash, $cart->contentHash())) {
            $this->clearCart($cart);

            return;
        }

        Log::info('Cart edited after checkout started; leaving it untouched', ['session_id' => $sessionId, 'cart_id' => $cart->id]);
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

        return Locales::isSupported($locale) ? $locale : self::FALLBACK_LOCALE;
    }

    protected function isPaid(array $session): bool
    {
        return ($session['payment_status'] ?? null) === 'paid';
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

    protected function requestOversoldAlert(Order $order): void
    {
        try {
            app(EmailService::class)->sendOversoldAlert($order);
        } catch (Throwable $e) {
            // Internal courtesy mail: never fail order creation because of it.
            Log::error('Oversold order alert could not be requested', [
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
