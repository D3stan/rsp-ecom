<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\User;
use App\Services\EmailService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;
use Stripe\Price;
use Stripe\Product;
use Stripe\Stripe;
use Stripe\Exception\ApiErrorException;
use App\Services\StripeService;

class CheckoutService
{
    /**
     * Calculate order totals from cart items
     */
    public function calculateTotals(Collection $cartItems, Cart $cart = null, array $appliedCoupon = null): array
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
        $taxRate = \App\Models\Setting::getTaxRate() / 100; // Get from settings and convert percentage to decimal
        $pricesIncludeTax = \App\Models\Setting::getPricesIncludeTax();

        // Calculate discount from applied coupon
        $discountAmount = 0;
        if ($appliedCoupon) {
            // Recalculate discount based on current subtotal for percentage discounts
            if ($appliedCoupon['discount_type'] === 'percentage' && $appliedCoupon['discount_percentage'] > 0) {
                $discountAmount = round($subtotal * ($appliedCoupon['discount_percentage'] / 100), 2);
            } else {
                $discountAmount = min($appliedCoupon['discount_amount'], $subtotal);
            }
        }

        // Calculate subtotal after discount
        $subtotalAfterDiscount = max(0, $subtotal - $discountAmount);

        if ($pricesIncludeTax) {
            // Prices include tax - calculate tax portion and tax-exclusive subtotal
            $taxAmount = round(($subtotalAfterDiscount * $taxRate) / (1 + $taxRate), 2);
            $subtotalExcludingTax = round($subtotalAfterDiscount / (1 + $taxRate), 2);
        } else {
            // Prices exclude tax - add tax on top
            $taxAmount = round($subtotalAfterDiscount * $taxRate, 2);
            $subtotalExcludingTax = $subtotalAfterDiscount;
        }

        // Use cart's shipping cost if available (considers size-based shipping)
        // Otherwise fall back to simple calculation
        $shippingCost = $cart ? $cart->shipping_cost : $this->calculateShipping($subtotal, $totalQuantity);

        // Calculate total based on whether prices include tax
        if ($pricesIncludeTax) {
            // Prices already include tax, so total = subtotal + shipping
            $total = $subtotalAfterDiscount + $shippingCost;
        } else {
            // Prices exclude tax, so total = subtotal + tax + shipping
            $total = $subtotalAfterDiscount + $taxAmount + $shippingCost;
        }

        return [
            'subtotal' => round($subtotal, 2), // Original subtotal before discount
            'discount_amount' => $discountAmount,
            'discount_description' => $appliedCoupon['description'] ?? null,
            'coupon_code' => $appliedCoupon['code'] ?? null,
            'subtotal_after_discount' => round($subtotalAfterDiscount, 2),
            'subtotal_excluding_tax' => round($subtotalExcludingTax, 2), // Tax-exclusive subtotal
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
    public function createCheckoutSession(User $user, Collection $cartItems, array $checkoutData, Cart $cart = null): Session
    {
        try {
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
                // The order is created after payment by OrderCreationService, from this metadata
                'metadata' => array_filter([
                    'user_id' => $user->id,
                    'cart_id' => $cart?->id,
                    'cart_items_count' => $cartItems->count(),
                    'order_type' => 'cart_checkout',
                    'locale' => app()->getLocale(),
                    'source' => OrderCreationService::SESSION_SOURCE,
                    'cart_hash' => $cart?->contentHash(),
                ], fn ($value) => $value !== null),
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

            // Apply promotion code if provided
            if (!empty($checkoutData['promotion_code'])) {
                $promotionService = app(PromotionService::class);
                $promotionService->applyPromotionCodeToSession($sessionParams, $checkoutData['promotion_code']);
            }

            // Create the checkout session
            return Session::create($sessionParams);

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
                    'unit_amount' => (int) round($product->price * 100), // Convert to cents
                ],
                'quantity' => $item->quantity,
            ];
        }

        return $lineItems;
    }

    /**
     * Retrieve a Stripe checkout session
     */
    public function retrieveCheckoutSession(string $sessionId): object
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
     * Create guest checkout session from cart items
     */
    public function createGuestCheckoutSessionFromCart(Collection $cartItems, array $guestData, string $guestSessionId): Session
    {
        try {
            $lineItems = $this->createStripeLineItems($cartItems);
            $totals = $this->calculateTotals($cartItems);

            $sessionParams = [
                'payment_method_types' => ['card'],
                'line_items' => $lineItems,
                'mode' => 'payment',
                'success_url' => route('checkout.success') . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('checkout.cancel'),
                'client_reference_id' => $guestSessionId,
                'customer_email' => $guestData['guest_email'],
                'metadata' => [
                    'guest_checkout' => 'true',
                    'guest_session_id' => $guestSessionId,
                    'guest_phone' => $guestData['guest_phone'] ?? '',
                    'locale' => app()->getLocale(),
                    'source' => OrderCreationService::SESSION_SOURCE,
                ],
            ];

            // Add billing address collection
            $sessionParams['billing_address_collection'] = 'required';

            // Add shipping address collection if different
            if (!$guestData['shipping_same_as_billing']) {
                $sessionParams['shipping_address_collection'] = [
                    'allowed_countries' => ['US', 'CA', 'GB', 'DE', 'FR', 'IT', 'ES'],
                ];
            }

            // Apply promotion code if provided
            if (!empty($guestData['promotion_code'])) {
                $promotionService = app(PromotionService::class);
                $promotionService->applyPromotionCodeToSession($sessionParams, $guestData['promotion_code']);
            }

            // Add tax ID collection if requested
            if ($guestData['collect_tax_id'] ?? false) {
                $sessionParams['tax_id_collection'] = ['enabled' => true];
            }

            $session = Session::create($sessionParams);

            Log::info('Guest checkout session created from cart', [
                'session_id' => $session->id,
                'guest_session_id' => $guestSessionId,
                'amount' => (int) round($totals['total'] * 100),
                'customer_email' => $guestData['guest_email'],
            ]);

            return $session;

        } catch (ApiErrorException $e) {
            Log::error('Failed to create guest checkout session from cart', [
                'error' => $e->getMessage(),
                'guest_session_id' => $guestSessionId,
            ]);
            throw $e;
        }
    }

    /**
     * Create guest checkout session
     */
    public function createGuestCheckoutSession(array $cartItems, array $guestData, string $guestSessionId, Cart $cart = null): Session
    {
        try {
            $lineItems = $this->createGuestLineItems($cartItems);
            $totals = $this->calculateGuestTotals($cartItems, $cart);

            $sessionParams = [
                'payment_method_types' => ['card'],
                'line_items' => $lineItems,
                'mode' => 'payment',
                'success_url' => route('checkout.success') . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('checkout.cancel'),
                'client_reference_id' => $guestSessionId,
                'customer_email' => $guestData['guest_email'],
                'metadata' => [
                    'guest_checkout' => 'true',
                    'guest_session_id' => $guestSessionId,
                    'guest_phone' => $guestData['guest_phone'] ?? '',
                    'locale' => app()->getLocale(),
                    'source' => OrderCreationService::SESSION_SOURCE,
                    'cart_hash' => $cart?->contentHash() ?? '',
                ],
            ];

            // Add billing address collection
            $sessionParams['billing_address_collection'] = 'required';

            // Add shipping address collection if different
            if (!$guestData['shipping_same_as_billing']) {
                $sessionParams['shipping_address_collection'] = [
                    'allowed_countries' => ['US', 'CA', 'GB', 'DE', 'FR', 'IT', 'ES'],
                ];
            }

            // Add promotion code support if provided
            if (!empty($guestData['promotion_code'])) {
                $promotionService = app(PromotionService::class);
                $promotionService->applyPromotionCodeToSession($sessionParams, $guestData['promotion_code']);
            }

            // Add tax ID collection if requested
            if ($guestData['collect_tax_id'] ?? false) {
                $sessionParams['tax_id_collection'] = ['enabled' => true];
            }

            $session = Session::create($sessionParams);

            Log::info('Guest checkout session created', [
                'session_id' => $session->id,
                'guest_session_id' => $guestSessionId,
                'amount' => (int) round($totals['total'] * 100),
                'customer_email' => $guestData['guest_email'],
            ]);

            return $session;

        } catch (ApiErrorException $e) {
            Log::error('Failed to create guest checkout session', [
                'error' => $e->getMessage(),
                'guest_session_id' => $guestSessionId,
            ]);
            throw $e;
        }
    }

    /**
     * Create Stripe line items from guest cart items
     */
    protected function createGuestLineItems(array $cartItems): array
    {
        $lineItems = [];

        foreach ($cartItems as $item) {
            $product = $item['product'];
            $size = $item['size'];
            $quantity = $item['quantity'];
            $price = $item['price'];

            $productName = $product->name;
            if ($size) {
                $productName .= ' - ' . $size->name;
            }

            $lineItems[] = [
                'price_data' => [
                    'currency' => 'usd',
                    'product_data' => [
                        'name' => $productName,
                        'description' => $product->description,
                        'images' => !empty($product->images) ? [asset('storage/' . $product->images[0])] : [asset('images/product.png')],
                        'metadata' => [
                            'product_id' => $product->id,
                            'size_id' => $size ? $size->id : null,
                        ],
                    ],
                    'unit_amount' => round($price * 100), // Convert to cents
                ],
                'quantity' => $quantity,
            ];
        }

        return $lineItems;
    }

    /**
     * Calculate totals for guest cart items
     */
    protected function calculateGuestTotals(array $cartItems, Cart $cart = null): array
    {
        $subtotal = 0;
        $totalQuantity = 0;

        foreach ($cartItems as $item) {
            $subtotal += $item['total'];
            $totalQuantity += $item['quantity'];
        }

        $taxRate = \App\Models\Setting::getTaxRate() / 100; // Get from settings and convert percentage to decimal
        $pricesIncludeTax = \App\Models\Setting::getPricesIncludeTax();
        
        if ($pricesIncludeTax) {
            // Prices include tax - calculate tax portion and tax-exclusive subtotal
            $taxAmount = round(($subtotal * $taxRate) / (1 + $taxRate), 2);
            $subtotalExcludingTax = round($subtotal / (1 + $taxRate), 2);
        } else {
            // Prices exclude tax - add tax on top
            $taxAmount = round($subtotal * $taxRate, 2);
            $subtotalExcludingTax = $subtotal;
        }
        
        // Use cart's shipping cost if available (considers size-based shipping)
        // Otherwise fall back to simple calculation
        $shippingCost = $cart ? $cart->shipping_cost : $this->calculateShipping($subtotal, $totalQuantity);
        $total = $subtotal + $shippingCost;

        return [
            'subtotal' => round($subtotal, 2), // Tax-inclusive subtotal (display price)
            'subtotal_excluding_tax' => round($subtotalExcludingTax, 2), // Tax-exclusive subtotal
            'tax_amount' => $taxAmount,
            'tax_rate' => $taxRate,
            'shipping_cost' => $shippingCost,
            'total' => round($total, 2),
            'total_quantity' => $totalQuantity,
        ];
    }
}
