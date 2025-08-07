<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\TrustProxies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: [
            TrustProxies::class,
        ]);

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'admin' => AdminMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Handle 404 Not Found errors
        $exceptions->render(function (NotFoundHttpException $e, $request) {
            if (!$request->expectsJson()) {
                return \Inertia\Inertia::render('Errors/NotFound')
                    ->toResponse($request)
                    ->setStatusCode(404);
            }
        });

        // Handle 403 Forbidden errors
        $exceptions->render(function (AccessDeniedHttpException $e, $request) {
            if (!$request->expectsJson()) {
                return \Inertia\Inertia::render('Errors/Forbidden')
                    ->toResponse($request)
                    ->setStatusCode(403);
            }
        });

        // Handle all HTTP exceptions (including abort(403), abort(500), etc.)
        $exceptions->render(function (HttpException $e, $request) {
            if (!$request->expectsJson()) {
                $statusCode = $e->getStatusCode();
                
                switch ($statusCode) {
                    case 403:
                        return \Inertia\Inertia::render('Errors/Forbidden')
                            ->toResponse($request)
                            ->setStatusCode(403);
                    case 500:
                        return \Inertia\Inertia::render('Errors/ServerError')
                            ->toResponse($request)
                            ->setStatusCode(500);
                    case 404:
                        return \Inertia\Inertia::render('Errors/NotFound')
                            ->toResponse($request)
                            ->setStatusCode(404);
                    default:
                        // For other HTTP errors, check if we should show custom page
                        if ($statusCode >= 500) {
                            return \Inertia\Inertia::render('Errors/ServerError')
                                ->toResponse($request)
                                ->setStatusCode($statusCode);
                        }
                        // Let Laravel handle other codes
                        return null;
                }
            }
        });

        // Handle general exceptions (non-HTTP exceptions)
        $exceptions->render(function (\Throwable $e, $request) {
            // Only handle in production or if APP_DEBUG=false
            if ((!app()->environment('local') || !config('app.debug')) && !$request->expectsJson()) {
                return \Inertia\Inertia::render('Errors/ServerError')
                    ->toResponse($request)
                    ->setStatusCode(500);
            }
        });
    })->create();
