# Laravel Cashier Installation and Configuration Guide

This document guides you through the Phase 1 implementation of Laravel Cashier with Stripe integration.

## Phase 1: Foundation Setup

### Prerequisites
- Laravel project setup
- Stripe account (test and live)
- Database connection configured

### Step 1: Install Laravel Cashier

```bash
composer require laravel/cashier
```

### Step 2: Publish and Run Migrations

```bash
# Publish Cashier migrations
php artisan vendor:publish --tag="cashier-migrations"

# Run the migrations (includes Cashier tables + custom Stripe fields for orders)
php artisan migrate
```

### Step 3: Environment Configuration

Copy the environment variables from `.env.cashier.example` to your `.env` file and update with your actual Stripe keys:

```env
# Stripe API Keys (Get these from your Stripe Dashboard)
STRIPE_KEY=pk_test_your_actual_publishable_key_here
STRIPE_SECRET=sk_test_your_actual_secret_key_here
STRIPE_WEBHOOK_SECRET=whsec_your_actual_webhook_secret_here

# Cashier Configuration
CASHIER_CURRENCY=usd
CASHIER_CURRENCY_LOCALE=it_IT
CASHIER_LOGGER=stack
```

### Step 4: Stripe Dashboard Setup

1. **Create Products and Prices**:
   - Go to Products in your Stripe Dashboard
   - Create products that match your e-commerce items
   - Note the Price IDs (they start with `price_`)

2. **Configure Webhooks**:
   - Go to Webhooks in your Stripe Dashboard
   - Add endpoint: `https://yourdomain.com/stripe/webhook`
   - Select events: `checkout.session.completed`, `payment_intent.succeeded`, `payment_intent.payment_failed`

### Step 5: Test the Installation

Create a simple test to verify Cashier is working:

```php
// In tinker or a test controller
$user = App\Models\User::first();
$customer = $user->createAsStripeCustomer();
dd($customer); // Should show Stripe customer object
```

## What's Been Implemented

### Database Changes
- ✅ Added `Billable` trait to User model
- ✅ Added Stripe fields to orders table:
  - `stripe_payment_intent_id`
  - `stripe_checkout_session_id` 
  - `payment_status`
  - `payment_method`
  - `stripe_customer_id`
- ✅ Created migration for Stripe fields
- ✅ Cashier migrations ready to be published

### Model Enhancements
- ✅ User model now supports Stripe billing
- ✅ Order model updated with payment helper methods:
  - `hasStripePaymentIntent()`
  - `isPaymentCompleted()`
  - `updatePaymentStatus()`
  - Payment status scopes

### Configuration
- ✅ AppServiceProvider configured for Cashier
- ✅ Environment variables example provided
- ✅ Documentation updated

## Next Steps (Phase 2)

After completing Phase 1:

1. Create CheckoutController
2. Implement basic product checkout
3. Create frontend checkout components
4. Test basic checkout flow

## Troubleshooting

### Common Issues

1. **Migration Errors**:
   - Ensure you've published Cashier migrations first
   - Check database connection

2. **Stripe Connection Issues**:
   - Verify API keys are correct
   - Check keys match your environment (test vs live)

3. **Environment Variables**:
   - Ensure no spaces around `=` in .env file
   - Clear config cache: `php artisan config:clear`

### Testing

Use Stripe's test card numbers:
- Success: `4242424242424242`
- Decline: `4000000000000002`
- SCA Required: `4000002500003155`

## Important Notes

- Never commit real Stripe keys to version control
- Use test keys in development
- Always validate payments server-side
- Implement proper error handling
- Follow PCI compliance guidelines
