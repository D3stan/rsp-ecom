<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Sentry\State\Scope;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

use function Sentry\configureScope;

/**
 * Gives every web request a reference that ties together its log lines, its Sentry events and the code a customer
 * can quote from the error page, and tags the request with who is behind it (admin, customer or guest).
 */
class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = (string) Str::ulid();
        $user = $request->user();
        $area = match (true) {
            $user === null => 'guest',
            $user->isAdmin() => 'admin',
            default => 'customer',
        };

        $request->attributes->set('request_id', $requestId);

        Log::shareContext(['request_id' => $requestId, 'user_id' => $user?->id, 'area' => $area]);

        try {
            configureScope(function (Scope $scope) use ($requestId, $user, $area): void {
                $scope->setTag('request_id', $requestId);
                $scope->setTag('area', $area);

                if ($user) {
                    $scope->setUser(['id' => (string) $user->id]);
                } else {
                    $scope->removeUser();
                }
            });
        } catch (Throwable) {
            // Error tracking is best effort: it must never take a request down.
        }

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
