<?php

use App\Http\Middleware\AuthenticateAppToken;
use App\Http\Middleware\AuthenticateForgeApiRequest;
use App\Http\Middleware\GitHttpAuthenticate;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Git HTTP Smart Protocol — no session, no CSRF, Basic Auth only
            // 120 requests/min per user/IP is generous for normal git operations
            Route::middleware('throttle:120,1')
                ->group(base_path('routes/git.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth.app_token' => AuthenticateAppToken::class,
            'git.auth' => GitHttpAuthenticate::class,
            'forge.api' => AuthenticateForgeApiRequest::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
