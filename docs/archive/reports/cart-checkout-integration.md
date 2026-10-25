# Cart Checkout Integration with Laravel Cashier

## Overview

This document explains how the cart-based checkout workflow has been integrated with Laravel Cashier's product-based checkout system.

## Problem Solved

**Original Issue**: The cart page had a "Proceed to Checkout" button that tried to redirect to `/checkout` and `/checkout/guest` routes that no longer existed after implementing proper Cashier methods.

**Solution**: Created bridge methods that convert cart items into Cashier-compatible checkout sessions.

## Implementation Details

### 1. **Cart Checkout Flow (Authenticated Users)**

**Route**: `POST /checkout/cart`
**Method**: `CheckoutController@cartCheckout`

**Process**:
1. Get user's cart with all items
2. Calculate total amount for entire cart
3. Create description of cart contents
4. Use Cashier's `checkoutCharge()` method for the total amount
5. Redirect to Stripe Checkout

**Code Example**:
```php
// Convert cart to single checkout charge
$totalAmountCents = (int)($totalAmount * 100);
$description = "2x T-Shirt (Large), 1x Jeans (Medium)";

$checkoutSession = $user->checkoutCharge($totalAmountCents, 'Cart Purchase', 1, [
    'success_url' => route('checkout.success') . '?session_id={CHECKOUT_SESSION_ID}',
    'cancel_url' => route('checkout.cancel'),
    'metadata' => [
        'cart_id' => $cart->id,
        'type' => 'cart_checkout',
        'description' => $description,
    ]
]);
```

### 2. **Guest Cart Checkout Flow**

**Route**: `POST /guest/checkout/cart`
**Method**: `CheckoutController@guestCartCheckout`

**Process**:
1. Get guest cart from session
2. Calculate total amount
3. Use Stripe API directly to create checkout session with line items
4. Redirect to Stripe Checkout

**Code Example**:
```php
$stripe = new \Stripe\StripeClient(config('cashier.secret'));

$checkoutSession = $stripe->checkout->sessions->create([
    'line_items' => [[
        'price_data' => [
            'currency' => 'usd',
            'product_data' => [
                'name' => 'Cart Purchase',
                'description' => $description,
            ],
            'unit_amount' => $totalAmountCents,
        ],
        'quantity' => 1,
    ]],
    'mode' => 'payment',
    'success_url' => route('checkout.success'),
    'cancel_url' => route('checkout.cancel'),
]);
```

### 3. **Frontend Integration**

**File**: `resources/js/pages/cart.tsx`

**Updated Code**:
```javascript
const proceedToCheckout = () => {
    if (auth?.user) {
        // User is authenticated, proceed to cart checkout using Cashier
        router.post('/checkout/cart');
    } else {
        // User is not authenticated, redirect to guest cart checkout
        router.post('/guest/checkout/cart');
    }
};
```

## Routes Added

```php
// Cart-based checkout routes (bridges cart workflow with Cashier)
Route::middleware(['auth'])->group(function () {
    Route::post('/checkout/cart', [CheckoutController::class, 'cartCheckout'])->name('checkout.cart');
});

// Guest cart checkout
Route::post('/guest/checkout/cart', [CheckoutController::class, 'guestCartCheckout'])->name('guest.cart.checkout');
```

## Benefits

### 1. **Seamless Integration**
- Existing cart workflow continues to work
- No frontend changes needed beyond route updates
- Users experience no difference in checkout flow

### 2. **Cashier Compatibility**
- Uses Cashier's built-in methods where possible
- Maintains Cashier's security and webhook handling
- Leverages Stripe's robust checkout system

### 3. **Metadata Tracking**
- Cart ID and user ID stored in Stripe metadata
- Easy to track which checkout came from cart vs individual product
- Detailed description of cart contents

## Checkout Session Metadata

**Authenticated User Cart**:
```json
{
    "cart_id": "123",
    "user_id": "456",
    "type": "cart_checkout",
    "description": "2x T-Shirt (Large), 1x Jeans (Medium)"
}
```

**Guest Cart**:
```json
{
    "guest_session_id": "sess_abc123",
    "type": "guest_cart_checkout", 
    "description": "1x T-Shirt, 1x Hat"
}
```

## Testing the Integration

### 1. **Authenticated User Test**
1. Add items to cart
2. Go to `/cart`
3. Click "Proceed to Checkout"
4. Should redirect to Stripe Checkout with cart total
5. Complete payment
6. Should redirect to success page

### 2. **Guest User Test**
1. Add items to cart (without logging in)
2. Go to `/cart`
3. Click "Proceed to Checkout"
4. Should redirect to Stripe Checkout with cart total
5. Complete payment
6. Should redirect to success page

## Error Handling

- Empty cart validation
- Stripe API error handling
- Proper error messages for users
- Logging for debugging

## Future Enhancements

1. **Order Creation**: Create Order records from successful cart checkouts
2. **Inventory Management**: Reduce stock after successful payment
3. **Email Notifications**: Send order confirmation emails
4. **Promotion Codes**: Add support for cart-level promotion codes

## Conclusion

The cart checkout integration successfully bridges the gap between a traditional cart-based ecommerce workflow and Laravel Cashier's product-based checkout system, providing a seamless user experience while leveraging Cashier's powerful features.