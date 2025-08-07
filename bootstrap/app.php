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
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e, $request) {
            if (!$request->expectsJson()) {
                return \Inertia\Inertia::render('Errors/Forbidden')
                    ->toResponse($request)
                    ->setStatusCode(403);
            }
        });

        // Handle 500 Internal Server errors
        $exceptions->render(function (HttpException $e, $request) {
            if ($e->getStatusCode() === 500 && !$request->expectsJson()) {
                return \Inertia\Inertia::render('Errors/ServerError')
                    ->toResponse($request)
                    ->setStatusCode(500);
            }
        });

        // Handle general exceptions in production
        $exceptions->render(function (\Throwable $e, $request) {
            if (app()->environment('production') && !$request->expectsJson()) {
                return \Inertia\Inertia::render('Errors/ServerError')
                    ->toResponse($request)
                    ->setStatusCode(500);
            }
        });
    })->create();
