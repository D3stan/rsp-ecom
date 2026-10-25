<?php

namespace App\Services;

use App\Exceptions\InvalidOrderTransition;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CheckoutSnapshot;
use App\Models\Order;
use App\Models\User;
use App\Support\Locales;
use App\Support\Vat;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\StripeObject;
use Throwable;

class OrderCreationService
{
    /**
     * @deprecated Alias kept for existing callers; the source of truth is config('app.supported_locales')
     *             (App\Support\Locales). A test pins both lists to the same set.
     */
    public const SUPPORTED_LOCALES = ['en', 'es', 'fr', 'de', 'it'];

    public const FALLBACK_LOCALE = 'it';

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

        if ($order->wasRecentlyCreated && ($session['metadata']['save_address'] ?? null) === '1') {
            $this->rememberShippingAddress($order);
        }

        if ($order->isPaymentCompleted()) {
            $this->requestConfirmationEmail($order);

            if ($order->wasRecentlyCreated || $order->wasChanged('payment_status')) {
                $this->requestNewOrderAlert($order);
            }

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

            $this->transitionOrSave($order, $order->can_be_cancelled ? 'cancelled' : null, ['source' => 'stripe', 'outcome' => $outcome]);

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
                    3
                );
            } catch (UniqueConstraintViolationException $e) {
                $existing = Order::where('stripe_checkout_session_id', $sessionId)->first();
                if ($existing) {
                    Log::info('Order already created concurrently for session', [
                        'session_id' => $sessionId,
                        'order_id' => $existing->id,
                    ]);

                    return $existing;
                }

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

        if ($updates) {
            $order->fill($updates);
            $this->transitionOrSave($order, $becamePaid && $order->status === 'pending' ? 'processing' : null, ['source' => 'stripe']);
        }

        if ($becamePaid) {
            Log::info('Order payment status updated to succeeded', ['order_id' => $order->id]);
            $this->clearCartAfterPayment($this->metadata($session), $session['id'] ?? null);
            app(StockService::class)->deductForOrder($order);
        }

        return $order;
    }

    /**
     * Webhook-driven status change: move the order to $to (null: just save the other changes). A move the lifecycle
     * refuses must never fail the webhook (Stripe would retry it for days): it is logged and the other changes
     * (payment fields) are saved without the status change.
     *
     * @param  array<string, mixed>  $meta
     */
    protected function transitionOrSave(Order $order, ?string $to, array $meta): void
    {
        if ($to === null) {
            $order->save();

            return;
        }

        try {
            $order->transitionTo($to, null, null, $meta);
        } catch (InvalidOrderTransition $e) {
            Log::warning('Stripe webhook status change refused by the order lifecycle; payment fields saved only', [
                'order_id' => $order->id,
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            $order->save();
        }
    }

    protected function createOrder(string $sessionId, array $session): Order
    {
        $metadata = $this->metadata($session);
        $paid = $this->isPaid($session);

        $userId = $this->resolveUserId($metadata);
        $isGuestOrder = $userId === null;

        $snapshot = $this->consumeSnapshot($metadata, $sessionId);
        $cart = $snapshot ? null : $this->trustedCart($metadata, $sessionId);
        $cartItems = $cart ? $cart->cartItems : collect();

        $addressParts = $this->parseShippingAddress($metadata['shipping_address'] ?? '');
        $entered = (array) $snapshot?->shipping_address;
        $billingAddress = $this->createAddress($session, $addressParts, 'billing', $entered);
        $shippingAddress = $this->createAddress($session, $addressParts, 'shipping', $entered);

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

    protected function createOrderItemsFromSession(Order $order, array $session): void
    {
        $lineItems = $this->fetchLineItems($session['id']);

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
     * Create an address record; the address as entered at checkout (snapshot) wins, then the metadata values,
     * with Stripe's customer details as the fallback.
     *
     * @param  array<string, mixed>  $entered
     */
    protected function createAddress(array $session, array $addressParts, string $type, array $entered = []): Address
    {
        $customerDetails = $session['customer_details'] ?? [];
        $address = is_array($customerDetails['address'] ?? null) ? $customerDetails['address'] : [];

        $nameParts = explode(' ', $addressParts['name'] ?: ($customerDetails['name'] ?? ''), 2);
        $firstName = $nameParts[0] ?? '';
        $lastName = $nameParts[1] ?? '';

        $statePostalParts = explode(' ', $addressParts['state_postal'] ?? '');
        $state = $statePostalParts[0] ?? '';
        $postalCode = $statePostalParts[1] ?? '';

        return Address::create(array_replace([
            'user_id' => null,
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
        ], array_filter(
            Arr::only($entered, Address::CHECKOUT_FIELDS),
            fn ($value) => is_string($value) && $value !== '',
        )));
    }

    protected function rememberShippingAddress(Order $order): void
    {
        try {
            if ($order->user && $order->shippingAddress) {
                $order->user->rememberShippingAddress($order->shippingAddress);
            }
        } catch (Throwable $e) {
            Log::warning('Could not save the order address as the customer default', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function requestConfirmationEmail(Order $order): void
    {
        try {
            $accepted = app(EmailService::class)->sendOrderConfirmation($order);

            if (! $accepted) {
                Log::warning('Order confirmation email was not accepted for delivery', ['order_id' => $order->id]);
            }
        } catch (Throwable $e) {
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
