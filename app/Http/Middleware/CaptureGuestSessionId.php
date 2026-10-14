<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Remembers, for the length of the request, the session id the request arrived with.
 *
 * Guest carts are keyed by the session id, and Auth::login() rotates it (session()->migrate()) before it fires the
 * Login event. The guest-cart merge on Login (App\Listeners\MergeGuestCartOnLogin) reads the original id from here.
 */
class CaptureGuestSessionId
{
    public const ATTRIBUTE = 'guest_session_id';

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasSession()) {
            $request->attributes->set(self::ATTRIBUTE, $request->session()->getId());
        }

        return $next($request);
    }
}
