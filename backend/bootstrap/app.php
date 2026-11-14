<?php

declare(strict_types=1);

use App\Support\Api\ApiExceptionRenderer;
use App\Support\Http\Middleware\AssignRequestId;
use App\Support\Http\Middleware\EnsureAccountIsActive;
use App\Support\Http\Middleware\SecurityHeaders;
use App\Support\Idempotency\EnsureIdempotency;
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
        $middleware->api(prepend: [
            AssignRequestId::class,
            SecurityHeaders::class,
        ]);

        $middleware->alias([
            'idempotent' => EnsureIdempotency::class,
            'active_account' => EnsureAccountIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return app(ApiExceptionRenderer::class)($e, $request);
            }
        });
    })->create();
