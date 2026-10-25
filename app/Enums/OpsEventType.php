<?php

namespace App\Enums;

/**
 * What App\Services\Ops\FunnelTracker records. The first three are the steps of the purchase funnel (a paid order
 * is the last step, read from the orders table); StripeWebhook marks every Stripe webhook the app received.
 */
enum OpsEventType: string
{
    case AddToCart = 'add_to_cart';
    case CheckoutStarted = 'checkout_started';
    case PaymentStarted = 'payment_started';
    case StripeWebhook = 'stripe_webhook';
}
