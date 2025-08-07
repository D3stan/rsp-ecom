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
                
                // Always show custom pages for test routes or specific conditions
                $isTestRoute = str_contains($request->path(), 'test-');
                $shouldShowCustomPage = $isTestRoute || !config('app.debug') || !app()->environment('local');
                
                if ($shouldShowCustomPage) {
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
                            // For other HTTP errors >= 500, show server error page
                            if ($statusCode >= 500) {
                                return \Inertia\Inertia::render('Errors/ServerError')
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
        $exceptions->render(function (\Throwable $e, $request) {
            if (!$request->expectsJson()) {
                $isTestRoute = str_contains($request->path(), 'test-');
                $shouldShowCustomPage = $isTestRoute || !config('app.debug') || !app()->environment('local');
                
                if ($shouldShowCustomPage) {
                    return \Inertia\Inertia::render('Errors/ServerError')
                        ->toResponse($request)
                        ->setStatusCode(500);
                }
            }
            
            // Return null to let Laravel handle it normally (debug page in development)
            return null;
        });
    })->create();
