<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes the request's scheme follow an https APP_URL, mirroring URL::forceScheme in AppServiceProvider.
 *
 * TLS ends at the proxy, the container's nginx sets no HTTPS param, and the proxy headers are untrusted when the
 * peer is a public address, so without this signed URLs and Secure cookies would break. Skipped locally, where the
 * app may run over plain http.
 */
class TrustAppUrlScheme
{
    public function handle(Request $request, Closure $next): Response
    {
        if (str_starts_with((string) config('app.url'), 'https://') && ! app()->environment('local', 'testing')) {
            $request->server->set('HTTPS', 'on');
        }

        return $next($request);
    }
}
