<?php

namespace App\Http\Middleware;

use App\Services\Ops\AbuseMonitor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Looks at what leaves the app, not at what comes in: a 429 means a rate limiter just refused someone, a 401/403
 * on a webhook endpoint means a forged or wrongly signed call. Watching only, never changes the response.
 */
class MonitorAbuse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            $status = $response->getStatusCode();

            if ($status === 429) {
                app(AbuseMonitor::class)->rateLimited($request->ip());
            } elseif (in_array($status, [401, 403], true) && $request->is('stripe/webhook')) {
                app(AbuseMonitor::class)->webhookRejected('Stripe');
            } elseif (in_array($status, [401, 403], true) && $request->is('webhooks/tracking/*')) {
                app(AbuseMonitor::class)->webhookRejected('carrier tracking');
            }
        } catch (Throwable) {
            // Monitoring is best effort: it must never change the response.
        }

        return $response;
    }
}
