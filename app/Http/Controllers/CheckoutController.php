<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidOrderTransition;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CheckoutSnapshot;
use App\Models\Order;
use App\Services\Newsletter\AbandonedCheckoutReminders;
use App\Services\OrderCreationService;
use App\Services\StripeService;
use App\Support\AddressRules;
use App\Support\CheckoutTotals;
use App\Support\ItalianProvinces;
use App\Support\Money;
use App\Support\Vat;
use Artesaos\SEOTools\Facades\SEOMeta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    public function success(Request $request): Response|RedirectResponse
    {
        $sessionId = $request->get('session_id');

        if (! $sessionId) {
            Log::warning('No session_id provided to success page');

            return redirect()->route('home')->with('error', 'Invalid checkout session. If you completed a payment, please check your email for confirmation or contact support.');
        }

        try {
            $stripe = StripeService::getClient();

            if (! $stripe) {
                Log::error('Stripe secret key not configured for checkout success');

                return redirect()->route('home')->with('error', 'Payment system not configured. Please contact support.');
            }

            $checkoutSession = $stripe->checkout->sessions->retrieve($sessionId);

            Log::info('Retrieved checkout session', [
                'session_id' => $sessionId,
                'payment_status' => $checkoutSession->payment_status,
            ]);

            $order = null;

            try {
                $order = app(OrderCreationService::class)->createFromStripeSession($checkoutSession);

                if (! $order) {
                    throw new \RuntimeException('Checkout session does not belong to this shop');
                }

                $order->load(['orderItems.product', 'orderItems.product.defaultVariant', 'orderItems.size', 'orderItems.productVariant']);
                $orderData = $order->toFrontendArray();

                if ($order->isPaymentCompleted()) {
                    session()->forget('applied_coupon');
                }
            } catch (\Exception $e) {
                Log::error('Failed to create order from session in success page', [
                    'session_id' => $sessionId,
                    'exception' => get_class($e),
                    'error' => $e->getMessage(),
                ]);

                if ($checkoutSession->payment_status === 'paid') {
                    $orderData = $this->createFallbackOrderData($checkoutSession);
                    Log::warning('Order creation failed but payment succeeded. Created fallback order data.', [
                        'session_id' => $sessionId,
                    ]);
                } else {
                    throw $e;
                }
            }

            $isGuest = ! $request->user() || ($order && $order->isGuestOrder());

            $isOwner = $order !== null
                && ($order->isOwnedBy($request->user(), session()->getId()) || $request->hasValidSignature());

            $orderData = Arr::only($orderData, [
                'id', 'order_number', 'status', 'payment_status', 'created_at', 'currency',
                'subtotal', 'tax_amount', 'tax_rate', 'shipping_amount', 'discount_amount', 'total_amount', 'orderItems',
            ]);

            return Inertia::render('Checkout/Success', [
                'order' => $orderData,
                'restricted' => ! $isOwner,
                'session' => [
                    'id' => $checkoutSession->id,
                    'payment_status' => $checkoutSession->payment_status,
                    'customer_email' => $isOwner
                        ? ($checkoutSession->customer_details->email ?? $checkoutSession->metadata->guest_email ?? '')
                        : '',
                    'customer_name' => $isOwner ? (string) ($order?->shippingAddress?->full_name ?? '') : '',
                    'amount_total' => (float) ($checkoutSession->amount_total / 100),
                    'currency' => $checkoutSession->currency,
                    'created' => $checkoutSession->created,
                ],
                'isGuest' => $isGuest,
                'user' => $request->user(),
            ]);

        } catch (\Exception $e) {
            Log::error('Checkout success handling failed: '.$e->getMessage(), [
                'session_id' => $sessionId,
                'user_id' => $request->user()?->id,
                'exception' => get_class($e),
            ]);

            $errorMessage = 'There was an issue retrieving your order details. ';

            if (str_contains($e->getMessage(), 'No such checkout session')) {
                $errorMessage .= 'The checkout session was not found. This may happen if the session has expired.';
            } else {
                $errorMessage .= 'If you completed a payment, please check your email for confirmation or contact support with session ID: '.$sessionId;
            }

            return redirect()->route('home')->with('error', $errorMessage);
        }
    }

    private function extractLineItemsFromSession(object $checkoutSession): array
    {
        try {
            $stripe = StripeService::getClient();

            if (! $stripe) {
                Log::error('Stripe secret key not configured for line items extraction');

                return [];
            }

            $lineItems = $stripe->checkout->sessions->allLineItems($checkoutSession->id);

            $items = [];
            foreach ($lineItems->data as $item) {
                $items[] = [
                    'id' => 'temp_'.uniqid(),
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
            Log::error('Failed to extract line items from session: '.$e->getMessage());

            return [];
        }
    }

    public function cancel(Request $request): Response
    {
        $sessionId = $request->get('session_id');
        $message = $request->get('message', 'Your payment was cancelled. You can try again anytime.');

        if ($sessionId) {
            $order = Order::where('stripe_checkout_session_id', $sessionId)->first();
            if ($order && $order->isPaymentPending()) {
                $order->payment_status = 'cancelled';
                try {
                    if ($order->can_be_cancelled) {
                        $order->transitionTo('cancelled', null, null, ['source' => 'checkout_cancel']);
                    } else {
                        $order->save();
                    }
                } catch (InvalidOrderTransition $e) {
                    Log::info('Checkout cancel left an order that can no longer be cancelled', ['order_id' => $order->id]);
                }
            } elseif ($order) {
                Log::info('Ignoring checkout cancel for an order whose payment is not pending', [
                    'order_id' => $order->id,
                    'payment_status' => $order->payment_status,
                ]);
            }
        }

        return Inertia::render('Checkout/Cancel', [
            'message' => $message,
        ]);
    }

    public function showDetails(Request $request): Response|RedirectResponse
    {
        SEOMeta::addMeta('robots', 'noindex,nofollow', 'name');
        SEOMeta::setTitle('Checkout Details – '.settings('store.name'));

        try {
            $user = $request->user();

            if (! $user) {
                return redirect()->route('guest.checkout.details');
            }

            $cart = $user->carts()->with(['cartItems.product', 'cartItems.product.defaultVariant', 'cartItems.productVariant', 'cartItems.size'])->latest()->first();

            if (! $cart || $cart->cartItems->isEmpty()) {
                return redirect()->route('cart')->with('error', 'Your cart is empty.');
            }

            $cart->refreshPrices();

            return Inertia::render('Checkout/Details', array_merge($this->detailsProps($cart), [
                'savedAddresses' => $user->addresses()->shipping()->orderByDesc('is_default')->orderByDesc('id')->get()
                    ->map(fn ($address) => $address->only(['id', 'is_default', ...Address::CHECKOUT_FIELDS]))->all(),
            ]));

        } catch (\Exception $e) {
            Log::error('Failed to show checkout details: '.$e->getMessage());

            return redirect()->route('cart')->with('error', 'Unable to load checkout page.');
        }
    }

    public function showGuestDetails(Request $request): Response|RedirectResponse
    {
        SEOMeta::addMeta('robots', 'noindex,nofollow', 'name');
        SEOMeta::setTitle('Guest Checkout – '.settings('store.name'));

        if ($response = $this->guestCheckoutDisabledResponse()) {
            return $response;
        }

        try {
            $guestSessionId = session()->getId();
            $cart = Cart::where('session_id', $guestSessionId)->with(['cartItems.product', 'cartItems.product.defaultVariant', 'cartItems.productVariant', 'cartItems.size'])->first();

            if (! $cart || $cart->cartItems->isEmpty()) {
                return redirect()->route('cart')->with('error', 'Your cart is empty.');
            }

            $cart->refreshPrices();

            return Inertia::render('Checkout/Details', $this->detailsProps($cart));

        } catch (\Exception $e) {
            Log::error('Failed to show guest checkout details: '.$e->getMessage());

            return redirect()->route('cart')->with('error', 'Unable to load checkout page.');
        }
    }

    private function detailsProps(Cart $cart): array
    {
        $totals = $this->checkoutTotals($cart);
        $appliedCoupon = session('applied_coupon');

        return [
            'cart' => ['id' => $cart->id],
            'cartItems' => $this->summaryLines($cart),
            'subtotal' => $totals['items'],
            'shippingCost' => $totals['shipping'],
            'taxAmount' => $totals['vat']['amount'],
            'taxRate' => $totals['vat']['rate'],
            'couponCode' => $appliedCoupon['code'] ?? null,
            'discountDescription' => $appliedCoupon['description'] ?? null,
            'discountAmount' => $totals['discount'],
            'total' => $totals['total'],
            'totalItems' => $cart->cartItems->sum('quantity'),
            'freeShippingRemaining' => $totals['free_shipping_remaining'],
            'provinces' => ItalianProvinces::ALL,
            'savedAddresses' => [],
        ];
    }

    /**
     * The lines of the order summary: what the customer sees, as plain values (no catalogue internals). The variant is
     * the line's own one, else the product's default (as in the checkout snapshot).
     *
     * @return array<int, array{id: int, name: string, variant_name: string|null, image: string, quantity: int, price: float, total: float}>
     */
    private function summaryLines(Cart $cart): array
    {
        return $cart->cartItems->map(function ($item) {
            $variant = $item->productVariant ?? $item->product?->defaultVariant;
            $price = round((float) $item->price, 2);

            return [
                'id' => $item->id,
                'name' => (string) ($item->product?->name ?? ''),
                'variant_name' => $variant?->name,
                'image' => ! empty($item->productVariant?->images)
                    ? $item->productVariant->main_image_url
                    : ($item->product?->image_url ?? '/images/product.png'),
                'quantity' => (int) $item->quantity,
                'price' => $price,
                'total' => round($price * (int) $item->quantity, 2),
            ];
        })->values()->all();
    }

    /**
     * What the customer pays (CheckoutTotals, shared with the cart page): used by both details pages and both
     * Stripe checkouts, so the snapshot, the Stripe amount and the stored order follow the free-shipping rule too.
     *
     * @return array{items: float, shipping: float, discount: float, total: float, vat: array{rate: float|null, amount: float}, free_shipping_remaining: float|null}
     */
    private function checkoutTotals(Cart $cart): array
    {
        return CheckoutTotals::for($cart, session('applied_coupon'));
    }

    private function checkoutRules(): array
    {
        return [
            'email' => 'required|email|max:255',
            'shipping_address' => 'required|array',
            ...AddressRules::for('shipping_address.'),
            'save_address' => 'sometimes|boolean',
            'order_notes' => ['nullable', 'string', 'max:'.(int) settings('orders.order_notes_max', 500)],
            'coupon_code' => 'nullable|string|max:50',
        ];
    }

    /**
     * orders.min_amount (0 = off): the items of the cart (before discount, without shipping) must reach it.
     *
     * @throws ValidationException
     */
    private function assertMinimumAmount(float $itemsTotal): void
    {
        $minimum = (float) settings('orders.min_amount', 0);

        if ($minimum > 0 && $itemsTotal < $minimum) {
            throw ValidationException::withMessages([
                'cart' => __('checkout.min_amount_error', ['amount' => Money::format($minimum, (string) settings('store.currency', 'EUR'))]),
            ]);
        }
    }

    private function guestCheckoutDisabledResponse(): ?RedirectResponse
    {
        if (settings('orders.guest_checkout_enabled', true)) {
            return null;
        }

        redirect()->setIntendedUrl(route('checkout.details'));

        return redirect()->route('login')->with('error', __('checkout.guest_checkout_disabled'));
    }

    private function bindSnapshotToSession(CheckoutSnapshot $snapshot, string $sessionId): void
    {
        try {
            $snapshot->update(['stripe_session_id' => $sessionId]);
        } catch (\Throwable $e) {
            Log::warning('Could not bind the checkout snapshot to the Stripe session', [
                'snapshot_id' => $snapshot->id,
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function metadataValue(string $value): string
    {
        return mb_substr($value, 0, 500);
    }

    /**
     * Shrink the oversell window: every line must be available in the requested quantity right now (its variant is
     * active and has the stock; the default variant for legacy lines). The paid order is still never rejected later.
     *
     * @throws ValidationException
     */
    private function assertAvailability(Cart $cart): void
    {
        foreach ($cart->cartItems as $item) {
            $variant = $item->productVariant ?? $item->product?->defaultVariant;
            $available = $variant
                ? $variant->canAddToCart((int) $item->quantity)
                : (bool) $item->product?->canAddToCart((int) $item->quantity);

            if (! $available) {
                throw ValidationException::withMessages([
                    'cart' => __('checkout.insufficient_stock_error', ['product' => $item->product?->name ?? '']),
                ]);
            }
        }
    }

    public function cartCheckout(Request $request): RedirectResponse|HttpResponse
    {
        try {
            $user = $request->user();

            if (! $user) {
                return redirect()->route('guest.cart.checkout');
            }

            $validated = $request->validate($this->checkoutRules());

            $cart = $user->carts()->with(['cartItems.product', 'cartItems.product.defaultVariant', 'cartItems.productVariant', 'cartItems.size'])->latest()->first();

            if (! $cart || $cart->cartItems->isEmpty()) {
                return redirect()->route('cart')->with('error', 'Your cart is empty.');
            }

            $priceChanges = $cart->refreshPrices();

            $totals = $this->checkoutTotals($cart);
            $this->assertMinimumAmount($totals['items']);
            $this->assertAvailability($cart);

            $totalAmountCents = (int) round($totals['total'] * 100);

            $description = $cart->cartItems->map(function ($item) {
                $sizeName = $item->size ? " ({$item->size->name})" : '';

                return $item->quantity.'x '.$item->product->name.$sizeName;
            })->join(', ');

            $shippingAddress = $validated['shipping_address'];
            $shippingAddressString = implode(', ', [
                $shippingAddress['first_name'].' '.$shippingAddress['last_name'],
                $shippingAddress['address_line_1'],
                $shippingAddress['city'],
                $shippingAddress['state'].' '.$shippingAddress['postal_code'],
                $shippingAddress['country'],
            ]);

            $snapshot = CheckoutSnapshot::capture($cart, $totals, $user->id, null, $validated['coupon_code'] ?? null, $priceChanges, $validated['shipping_address']);
            app(AbandonedCheckoutReminders::class)->rememberContact($snapshot, $request, $validated['email'], $user);

            try {
                $checkoutSession = $user->checkoutCharge($totalAmountCents, 'Cart Purchase', 1, [
                    'success_url' => route('checkout.success').'?session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url' => route('checkout.cancel'),
                    'expires_at' => app(AbandonedCheckoutReminders::class)->sessionExpiresAt(),
                    'saved_payment_method_options' => ['payment_method_save' => 'enabled'],
                    'metadata' => [
                        'cart_id' => $cart->id,
                        'user_id' => $user->id,
                        'user_email' => $validated['email'],
                        'type' => 'cart_checkout',
                        'snapshot_id' => $snapshot->id,
                        'description' => $this->metadataValue($description),
                        'shipping_address' => $this->metadataValue($shippingAddressString),
                        'customer_phone' => $validated['shipping_address']['phone'] ?? '',
                        'order_notes' => $validated['order_notes'] ?? '',
                        'coupon_code' => $validated['coupon_code'] ?? '',
                        'save_address' => $request->boolean('save_address') ? '1' : '0',
                        'locale' => app()->getLocale(),
                        'source' => OrderCreationService::SESSION_SOURCE,
                        'cart_hash' => $cart->contentHash(),
                    ],
                ]);
            } catch (\Throwable $e) {
                $snapshot->delete();

                throw $e;
            }

            $this->bindSnapshotToSession($snapshot, (string) $checkoutSession->id);

            if ($request->header('X-Inertia')) {
                return Inertia::location($checkoutSession->url);
            }

            return redirect($checkoutSession->url);

        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Cart checkout failed: '.$e->getMessage(), [
                'user_id' => $request->user()?->id,
                'exception' => get_class($e),
            ]);

            return redirect()->back()->with('error', 'Unable to create checkout session. Please try again.');
        }
    }

    public function guestCartCheckout(Request $request): RedirectResponse|HttpResponse
    {
        if ($response = $this->guestCheckoutDisabledResponse()) {
            return $response;
        }

        if (! StripeService::isConfigured()) {
            Log::error('Stripe not properly configured for guest checkout', [
                'secret_key_exists' => ! empty(StripeService::getSecretKey()),
                'public_key_exists' => ! empty(StripeService::getPublicKey()),
                'env_secret' => ! empty(config('cashier.secret')),
                'env_public' => ! empty(config('cashier.key')),
            ]);

            return redirect()->back()->with('error', 'Payment system not configured. Please contact support.');
        }

        try {
            $validated = $request->validate($this->checkoutRules());

            $guestSessionId = session()->getId();
            $cart = Cart::where('session_id', $guestSessionId)->with(['cartItems.product', 'cartItems.product.defaultVariant', 'cartItems.productVariant', 'cartItems.size'])->first();

            if (! $cart || $cart->cartItems->isEmpty()) {
                return redirect()->route('cart')->with('error', 'Your cart is empty.');
            }

            $priceChanges = $cart->refreshPrices();

            $totals = $this->checkoutTotals($cart);
            $this->assertMinimumAmount($totals['items']);
            $this->assertAvailability($cart);

            $totalAmountCents = (int) round($totals['total'] * 100);

            $description = $cart->cartItems->map(function ($item) {
                $sizeName = $item->size ? " ({$item->size->name})" : '';

                return $item->quantity.'x '.$item->product->name.$sizeName;
            })->join(', ');

            $shippingAddress = $validated['shipping_address'];
            $shippingAddressString = implode(', ', [
                $shippingAddress['first_name'].' '.$shippingAddress['last_name'],
                $shippingAddress['address_line_1'],
                $shippingAddress['city'],
                $shippingAddress['state'].' '.$shippingAddress['postal_code'],
                $shippingAddress['country'],
            ]);

            $stripe = StripeService::getClient();

            if (! $stripe) {
                Log::error('Stripe secret key not configured for guest checkout');

                return redirect()->back()->with('error', 'Payment system not configured. Please contact support.');
            }

            $snapshot = CheckoutSnapshot::capture($cart, $totals, null, $guestSessionId, $validated['coupon_code'] ?? null, $priceChanges, $validated['shipping_address']);
            app(AbandonedCheckoutReminders::class)->rememberContact($snapshot, $request, $validated['email']);

            try {
                $checkoutSession = $stripe->checkout->sessions->create([
                    'line_items' => [[
                        'price_data' => [
                            'currency' => config('cashier.currency'),
                            'product_data' => [
                                'name' => 'Cart Purchase',
                                'description' => $this->metadataValue($description),
                            ],
                            'unit_amount' => $totalAmountCents,
                        ],
                        'quantity' => 1,
                    ]],
                    'mode' => 'payment',
                    'success_url' => route('checkout.success').'?session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url' => route('checkout.cancel'),
                    'expires_at' => app(AbandonedCheckoutReminders::class)->sessionExpiresAt(),
                    'customer_email' => $validated['email'],
                    'metadata' => [
                        'guest_session_id' => $guestSessionId,
                        'guest_email' => $validated['email'],
                        'type' => 'guest_cart_checkout',
                        'snapshot_id' => $snapshot->id,
                        'description' => $this->metadataValue($description),
                        'shipping_address' => $this->metadataValue($shippingAddressString),
                        'customer_phone' => $validated['shipping_address']['phone'] ?? '',
                        'order_notes' => $validated['order_notes'] ?? '',
                        'coupon_code' => $validated['coupon_code'] ?? '',
                        'locale' => app()->getLocale(),
                        'source' => OrderCreationService::SESSION_SOURCE,
                        'cart_hash' => $cart->contentHash(),
                    ],
                ]);
            } catch (\Throwable $e) {
                $snapshot->delete();

                throw $e;
            }

            $this->bindSnapshotToSession($snapshot, (string) $checkoutSession->id);

            if ($request->header('X-Inertia')) {
                return Inertia::location($checkoutSession->url);
            }

            return redirect($checkoutSession->url);

        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Guest cart checkout failed: '.$e->getMessage());

            return redirect()->back()->with('error', 'Unable to process guest checkout. Please try again.');
        }
    }

    private function createFallbackOrderData(object $checkoutSession): array
    {
        $orderItems = $this->extractLineItemsFromSession($checkoutSession);

        $total = (float) ($checkoutSession->amount_total / 100);
        $vat = Vat::includedIn($total);

        return [
            'id' => 'pending',
            'order_number' => 'PENDING',
            'total_amount' => $total,
            'status' => 'processing',
            'payment_status' => 'succeeded',
            'subtotal' => (float) (($checkoutSession->amount_subtotal ?? $checkoutSession->amount_total) / 100),
            'tax_amount' => $vat['amount'],
            'tax_rate' => $vat['rate'],
            'shipping_amount' => (float) (($checkoutSession->total_details->amount_shipping ?? 0) / 100),
            'currency' => strtoupper($checkoutSession->currency),
            'created_at' => now()->toISOString(),
            'orderItems' => $orderItems,
        ];
    }
}
