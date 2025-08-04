<?php

use App\Http\Middleware\AuthenticateMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use \Illuminate\Auth\Middleware\Authenticate;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        api: __DIR__ . '/../routes/api.php',
        health: '/up',
    )
    ->withMiddleware(callback: function (Middleware $middleware) {
    })
    ->withBindings([
        Authenticate::class => AuthenticateMiddleware::class
    ])
    ->withExceptions(function (Exceptions $exceptions) {
    })->create();
