<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        \App\Console\Commands\SeedCommand::class,
    ])
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'auth.token' => \App\Http\Middleware\TokenAuthMiddleware::class,
            'tenant' => \App\Http\Middleware\TenantMiddleware::class,
        ]);
        $middleware->prepend(\App\Http\Middleware\ForceJsonResponse::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            return response()->json(['message' => 'Não autenticado'], 401);
        });
    })->create();
