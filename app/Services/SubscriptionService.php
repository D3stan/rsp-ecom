<?php

namespace App\Services;

use App\Models\User;
use App\Models\Product;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Exceptions\IncompletePayment;
use Stripe\Exception\ApiErrorException;
use Stripe\Checkout\Session;
use Stripe\Price;
use Stripe\Subscription;

class SubscriptionService
{
    /**
     * Create a subscription checkout session
     */
    public function createSubscriptionCheckoutSession(
        User $user, 
        Product $product, 
        string $priceId, 
        array $options = []
    ): Session {
        try {
            // Ensure user has a Stripe customer
            if (!$user->hasStripeId()) {
                $user->createAsStripeCustomer();
            }

            $sessionParams = [
                'customer' => $user->stripe_id,
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price' => $priceId,
                    'quantity' => 1,
                ]],
                'mode' => 'subscription',
                'success_url' => route('subscription.success') . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('subscription.cancel'),
                'metadata' => [
                    'user_id' => $user->id,
                    'product_id' => $product->id,
                    'subscription_type' => $options['subscription_type'] ?? 'standard',
                ],
            ];

            // Add trial period if specified
            if (isset($options['trial_days']) && $options['trial_days'] > 0) {
                $sessionParams['subscription_data'] = [
                    'trial_period_days' => $options['trial_days'],
                ];
            }

            // Add promotion code support
            if (!empty($options['promotion_code'])) {
                $sessionParams['discounts'] = [
                    ['coupon' => $options['promotion_code']]
                ];
            }

            // Allow promotion codes
            if ($options['allow_promotion_codes'] ?? true) {
                $sessionParams['allow_promotion_codes'] = true;
            }

            // Add tax ID collection for business subscriptions
            if ($options['collect_tax_id'] ?? false) {
                $sessionParams['tax_id_collection'] = ['enabled' => true];
            }

            // Set billing address collection
            $sessionParams['billing_address_collection'] = 'required';

            $session = Session::create($sessionParams);

            Log::info('Subscription checkout session created', [
                'session_id' => $session->id,
                'user_id' => $user->id,
                'product_id' => $product->id,
                'price_id' => $priceId,
            ]);

            return $session;

        } catch (ApiErrorException $e) {
            Log::error('Failed to create subscription checkout session', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'product_id' => $product->id,
            ]);
            throw $e;
        }
    }

    /**
     * Create a subscription directly with payment method
     */
    public function createSubscription(
        User $user, 
        string $priceId, 
        string $paymentMethodId, 
        array $options = []
    ): Subscription {
        try {
            // Ensure user has Stripe customer
            if (!$user->hasStripeId()) {
                $user->createAsStripeCustomer();
            }

            // Attach payment method to customer
            $paymentMethod = $user->addPaymentMethod($paymentMethodId);
            $user->updateDefaultPaymentMethod($paymentMethod);

            // Create subscription
            $subscriptionBuilder = $user->newSubscription('default', $priceId);

            // Add trial period if specified
            if (isset($options['trial_days']) && $options['trial_days'] > 0) {
                $subscriptionBuilder->trialDays($options['trial_days']);
            }

            // Add metadata
            if (isset($options['metadata'])) {
                $subscriptionBuilder->withMetadata($options['metadata']);
            }

            $subscription = $subscriptionBuilder->create($paymentMethod);

            Log::info('Subscription created', [
                'subscription_id' => $subscription->id,
                'user_id' => $user->id,
                'price_id' => $priceId,
            ]);

            return $subscription;

        } catch (IncompletePayment $exception) {
            Log::warning('Subscription requires payment confirmation', [
                'payment_intent' => $exception->payment->id,
                'user_id' => $user->id,
            ]);
            throw $exception;
        } catch (ApiErrorException $e) {
            Log::error('Failed to create subscription', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'price_id' => $priceId,
            ]);
            throw $e;
        }
    }

    /**
     * Cancel a subscription
     */
    public function cancelSubscription(User $user, string $subscriptionName = 'default'): bool
    {
        try {
            $subscription = $user->subscription($subscriptionName);
            
            if (!$subscription) {
                Log::warning('Subscription not found for cancellation', [
                    'user_id' => $user->id,
                    'subscription_name' => $subscriptionName,
                ]);
                return false;
            }

            $subscription->cancel();

            Log::info('Subscription cancelled', [
                'subscription_id' => $subscription->stripe_id,
                'user_id' => $user->id,
            ]);

            return true;

        } catch (ApiErrorException $e) {
            Log::error('Failed to cancel subscription', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);
            return false;
        }
    }

    /**
     * Resume a subscription
     */
    public function resumeSubscription(User $user, string $subscriptionName = 'default'): bool
    {
        try {
            $subscription = $user->subscription($subscriptionName);
            
            if (!$subscription) {
                Log::warning('Subscription not found for resuming', [
                    'user_id' => $user->id,
                    'subscription_name' => $subscriptionName,
                ]);
                return false;
            }

            $subscription->resume();

            Log::info('Subscription resumed', [
                'subscription_id' => $subscription->stripe_id,
                'user_id' => $user->id,
            ]);

            return true;

        } catch (ApiErrorException $e) {
            Log::error('Failed to resume subscription', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);
            return false;
        }
    }

    /**
     * Change subscription plan
     */
    public function changeSubscriptionPlan(
        User $user, 
        string $newPriceId, 
        string $subscriptionName = 'default',
        bool $prorate = true
    ): bool {
        try {
            $subscription = $user->subscription($subscriptionName);
            
            if (!$subscription) {
                Log::warning('Subscription not found for plan change', [
                    'user_id' => $user->id,
                    'subscription_name' => $subscriptionName,
                ]);
                return false;
            }

            if ($prorate) {
                $subscription->swap($newPriceId);
            } else {
                $subscription->noProrate()->swap($newPriceId);
            }

            Log::info('Subscription plan changed', [
                'subscription_id' => $subscription->stripe_id,
                'user_id' => $user->id,
                'new_price_id' => $newPriceId,
                'prorate' => $prorate,
            ]);

            return true;

        } catch (ApiErrorException $e) {
            Log::error('Failed to change subscription plan', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'new_price_id' => $newPriceId,
            ]);
            return false;
        }
    }

    /**
     * Get subscription plans for a product
     */
    public function getSubscriptionPlans(Product $product): array
    {
        try {
            // This assumes you have subscription plans configured in Stripe
            // and metadata linking them to products
            $prices = Price::all([
                'product' => $product->stripe_product_id ?? $product->id,
                'active' => true,
                'type' => 'recurring',
            ]);

            $plans = [];
            foreach ($prices->data as $price) {
                $plans[] = [
                    'id' => $price->id,
                    'amount' => $price->unit_amount,
                    'currency' => $price->currency,
                    'interval' => $price->recurring->interval,
                    'interval_count' => $price->recurring->interval_count,
                    'nickname' => $price->nickname,
                    'product_id' => $price->product,
                ];
            }

            return $plans;

        } catch (ApiErrorException $e) {
            Log::error('Failed to fetch subscription plans', [
                'error' => $e->getMessage(),
                'product_id' => $product->id,
            ]);
            return [];
        }
    }

    /**
     * Get user's active subscriptions
     */
    public function getUserSubscriptions(User $user): array
    {
        try {
            $subscriptions = [];
            
            foreach ($user->subscriptions as $subscription) {
                $subscriptions[] = [
                    'id' => $subscription->stripe_id,
                    'name' => $subscription->name,
                    'status' => $subscription->stripe_status,
                    'current_period_start' => $subscription->asStripeSubscription()->current_period_start,
                    'current_period_end' => $subscription->asStripeSubscription()->current_period_end,
                    'cancel_at_period_end' => $subscription->cancelled(),
                    'trial_ends_at' => $subscription->trial_ends_at,
                ];
            }

            return $subscriptions;

        } catch (\Exception $e) {
            Log::error('Failed to fetch user subscriptions', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);
            return [];
        }
    }
}
