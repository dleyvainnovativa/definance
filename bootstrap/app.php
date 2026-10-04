<?php

use App\Exceptions\PostingException;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'firebase.auth' => \App\Http\Middleware\FirebaseAuth::class,
            'firebase.jwt' => \App\Http\Middleware\FirebaseJWTAuth::class,
        ]);

        // Rate-limit the API (limiter defined in AppServiceProvider).
        $middleware->throttleApi();

        // Security headers on every response.
        $middleware->web(append: [SecurityHeaders::class]);
        $middleware->api(append: [SecurityHeaders::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Domain posting failures become a clean 422 for API clients.
        $exceptions->render(function (PostingException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return null;
        });
    })->create();
