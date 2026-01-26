<?php

use App\Http\Middleware\EnsureNotBanned;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\SecurityHeaders;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'not-banned' => EnsureNotBanned::class,
        ]);
        
        $middleware->validateCsrfTokens(except: [
    'paddle/webhook',
    'webhooks/paddle',
]);

        // Apply to all web requests; the middleware only acts when a user is authenticated
        $middleware->web(append: [
            EnsureNotBanned::class,
            \App\Http\Middleware\SetUserTimezone::class,
        ]);
        
        $middleware->web(append: [
        SecurityHeaders::class,
            ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->create();