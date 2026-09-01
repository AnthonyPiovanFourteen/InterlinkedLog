<?php

use App\Console\Commands\SeedCommand;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\TenantMiddleware;
use App\Http\Middleware\TokenAuthMiddleware;
use Illuminate\Auth\AuthenticationException;
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
        SeedCommand::class,
    ])
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'auth.token' => TokenAuthMiddleware::class,
            'tenant' => TenantMiddleware::class,
        ]);
        $middleware->prepend(ForceJsonResponse::class);
        // Único proxy confiado: o container do frontend (IP fixo na rede
        // docker, ver docker-compose.yml). O nginx->php-fpm via FastCGI não
        // injeta X-Forwarded-For; confiar em qualquer outra origem permitiria
        // forjar o IP e escapar do rate limiting.
        $middleware->trustProxies(at: ['172.28.0.10']);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (AuthenticationException $e, $request) {
            return response()->json(['message' => 'Não autenticado'], 401);
        });
    })->create();
