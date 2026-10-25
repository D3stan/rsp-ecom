<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\CaptureGuestSessionId;
use App\Http\Middleware\DetectLanguage;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\MonitorAbuse;
use App\Http\Middleware\ShareTranslations;
use App\Http\Middleware\TrustAppUrlScheme;
use App\Services\Ops\TelegramNotifier;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Sentry\Laravel\Integration;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Traefik (and the container's own nginx) sit in the Docker private ranges. Trust only those, and only for
        // the client IP and scheme: Host, Prefix and Port are deliberately not trusted.
        $middleware->trustProxies(
            at: ['10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', 'fc00::/7'],
            headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO,
        );

        // The scheme comes from APP_URL, whatever the peer address or proxy headers say
        $middleware->prepend(TrustAppUrlScheme::class);

        // Counts 429s and rejected webhooks as abuse signals (watching only)
        $middleware->append(MonitorAbuse::class);

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            DetectLanguage::class,
            ShareTranslations::class,
            EnsureUserIsActive::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            CaptureGuestSessionId::class,
            AssignRequestId::class,
        ]);

        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'auth' => Authenticate::class,
        ]);

        // Server-to-server webhooks cannot carry a CSRF token: carrier webhooks are authenticated by the driver, the
        // Stripe webhook by its signature (Cashier's VerifyWebhookSignature)
        $middleware->preventRequestForgery(except: ['webhooks/tracking/*', 'stripe/webhook', 'newsletter/unsubscribe/*', 'restock/unsubscribe/*']);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        Integration::handles($exceptions);

        // Sentry's free plan has no chat integration: push uncaught exceptions to Telegram ourselves (throttled)
        $exceptions->report(function (Throwable $e) {
            app(TelegramNotifier::class)->notifyException($e);
        });

        $exceptions->render(function (PostTooLargeException $e, $request) {
            $message = 'Upload troppo grande: riduci il peso totale delle immagini e riprova.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 413);
            }

            return back()->withErrors(['images' => $message]);
        });

        // Handle all HTTP exceptions (including abort(403), abort(500), etc.)
        $exceptions->render(function (HttpException $e, $request) {
            if (! $request->expectsJson()) {
                $statusCode = $e->getStatusCode();

                $isTesting = app()->environment('testing');
                $isDebugEnv = config('app.debug') && app()->environment('local', 'development');
                $shouldShowCustomPage = ! $isTesting && ! $isDebugEnv;

                if ($shouldShowCustomPage) {
                    switch ($statusCode) {
                        case 403:
                            return Inertia::render('Errors/Forbidden')
                                ->toResponse($request)
                                ->setStatusCode(403);
                        case 500:
                            return Inertia::render('Errors/ServerError', ['requestId' => $request->attributes->get('request_id')])
                                ->toResponse($request)
                                ->setStatusCode(500);
                        case 404:
                            return Inertia::render('Errors/NotFound')
                                ->toResponse($request)
                                ->setStatusCode(404);
                        default:
                            // For other HTTP errors >= 500, show server error page
                            if ($statusCode >= 500) {
                                return Inertia::render('Errors/ServerError', ['requestId' => $request->attributes->get('request_id')])
                                    ->toResponse($request)
                                    ->setStatusCode($statusCode);
                            }
                    }
                }
            }

            // Return null to let Laravel handle it normally (debug page in development)
            return null;
        });

        // Handle general exceptions (non-HTTP exceptions)
        $exceptions->render(function (Throwable $e, $request) {
            // Laravel turns these into a redirect or a plain HTTP response itself (guest to login, 419, 405, 429, ...)
            if ($e instanceof ValidationException
                || $e instanceof AuthenticationException
                || $e instanceof HttpExceptionInterface) {
                return null;
            }

            if (! $request->expectsJson()) {
                $isTestRoute = str_contains($request->path(), 'test-');
                $isTesting = app()->environment('testing');
                $isDebugEnv = config('app.debug') && app()->environment('local', 'development');
                $shouldShowCustomPage = ! $isTesting && ! $isDebugEnv;

                if ($shouldShowCustomPage) {
                    return Inertia::render('Errors/ServerError', ['requestId' => $request->attributes->get('request_id')])
                        ->toResponse($request)
                        ->setStatusCode(500);
                }
            }

            // Return null to let Laravel handle it normally (debug page in development)
            return null;
        });
    })->create();
