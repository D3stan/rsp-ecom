<?php

namespace App\Http\Controllers;

use App\Services\SubscriptionService;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Laravel\Cashier\Exceptions\IncompletePayment;

class SubscriptionController extends Controller
{
    protected SubscriptionService $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Display subscription plans for a product
     */
    public function plans(Product $product)
    {
        $plans = $this->subscriptionService->getSubscriptionPlans($product);

        return Inertia::render('Subscription/Plans', [
            'product' => $product,
            'plans' => $plans,
        ]);
    }

    /**
     * Create subscription checkout session
     */
    public function createCheckoutSession(Request $request)
    {
        $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'price_id' => 'required|string',
            'trial_days' => 'nullable|integer|min:0|max:365',
            'promotion_code' => 'nullable|string|max:50',
            'collect_tax_id' => 'boolean',
        ]);

        $user = Auth::user();
        $product = Product::findOrFail($request->product_id);

        try {
            $session = $this->subscriptionService->createSubscriptionCheckoutSession(
                $user,
                $product,
                $request->price_id,
                [
                    'trial_days' => $request->trial_days,
                    'promotion_code' => $request->promotion_code,
                    'collect_tax_id' => $request->collect_tax_id ?? false,
                    'subscription_type' => $request->subscription_type ?? 'standard',
                ]
            );

            return response()->json([
                'checkout_url' => $session->url,
                'session_id' => $session->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Subscription checkout failed: ' . $e->getMessage());
            return response()->json(['error' => 'Subscription checkout failed. Please try again.'], 500);
        }
    }

    /**
     * Create subscription with payment method
     */
    public function create(Request $request)
    {
        $request->validate([
            'price_id' => 'required|string',
            'payment_method_id' => 'required|string',
            'trial_days' => 'nullable|integer|min:0|max:365',
            'product_id' => 'nullable|integer|exists:products,id',
        ]);

        $user = Auth::user();

        try {
            $subscription = $this->subscriptionService->createSubscription(
                $user,
                $request->price_id,
                $request->payment_method_id,
                [
                    'trial_days' => $request->trial_days,
                    'metadata' => [
                        'product_id' => $request->product_id,
                        'created_via' => 'web',
                    ],
                ]
            );

            return response()->json([
                'success' => true,
                'subscription_id' => $subscription->id,
                'redirect_url' => route('subscription.success'),
            ]);

        } catch (IncompletePayment $e) {
            return response()->json([
                'requires_action' => true,
                'payment_intent' => [
                    'id' => $e->payment->id,
                    'client_secret' => $e->payment->client_secret,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Subscription creation failed: ' . $e->getMessage());
            return response()->json(['error' => 'Subscription creation failed. Please try again.'], 500);
        }
    }

    /**
     * Display user's subscriptions
     */
    public function index()
    {
        $user = Auth::user();
        $subscriptions = $this->subscriptionService->getUserSubscriptions($user);

        return Inertia::render('Subscription/Index', [
            'subscriptions' => $subscriptions,
            'user' => $user,
        ]);
    }

    /**
     * Cancel a subscription
     */
    public function cancel(Request $request)
    {
        $request->validate([
            'subscription_name' => 'nullable|string',
        ]);

        $user = Auth::user();
        $subscriptionName = $request->subscription_name ?? 'default';

        $success = $this->subscriptionService->cancelSubscription($user, $subscriptionName);

        if ($success) {
            return response()->json(['success' => true, 'message' => 'Subscription cancelled successfully.']);
        }

        return response()->json(['error' => 'Failed to cancel subscription.'], 500);
    }

    /**
     * Resume a subscription
     */
    public function resume(Request $request)
    {
        $request->validate([
            'subscription_name' => 'nullable|string',
        ]);

        $user = Auth::user();
        $subscriptionName = $request->subscription_name ?? 'default';

        $success = $this->subscriptionService->resumeSubscription($user, $subscriptionName);

        if ($success) {
            return response()->json(['success' => true, 'message' => 'Subscription resumed successfully.']);
        }

        return response()->json(['error' => 'Failed to resume subscription.'], 500);
    }

    /**
     * Change subscription plan
     */
    public function changePlan(Request $request)
    {
        $request->validate([
            'new_price_id' => 'required|string',
            'subscription_name' => 'nullable|string',
            'prorate' => 'boolean',
        ]);

        $user = Auth::user();
        $subscriptionName = $request->subscription_name ?? 'default';
        $prorate = $request->prorate ?? true;

        $success = $this->subscriptionService->changeSubscriptionPlan(
            $user,
            $request->new_price_id,
            $subscriptionName,
            $prorate
        );

        if ($success) {
            return response()->json(['success' => true, 'message' => 'Subscription plan changed successfully.']);
        }

        return response()->json(['error' => 'Failed to change subscription plan.'], 500);
    }

    /**
     * Handle successful subscription
     */
    public function success(Request $request)
    {
        $sessionId = $request->query('session_id');
        
        if ($sessionId) {
            // Retrieve the session and create any necessary records
            // This is handled by webhooks, but we can show a success message
            return Inertia::render('Subscription/Success', [
                'session_id' => $sessionId,
            ]);
        }

        return Inertia::render('Subscription/Success');
    }

    /**
     * Handle cancelled subscription checkout
     */
    public function cancelCheckout()
    {
        return Inertia::render('Subscription/Cancel');
    }
}
