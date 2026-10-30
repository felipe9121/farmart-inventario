<?php

declare(strict_types=1);

use App\Http\Middleware\RequiereIdempotencyKey;
use App\Http\Middleware\SinCache;
use App\Http\Middleware\UsuarioActivo;
use App\Support\ErrorBaseDatos;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(append: [SinCache::class]);

        $middleware->alias([
            'idempotencia' => RequiereIdempotencyKey::class,
            'activo' => UsuarioActivo::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // RN-10: los errores de BD se registran sin el mensaje original, que trae datos.
        $exceptions->report(function (QueryException $e): void {
            Log::error('Error de base de datos.', ErrorBaseDatos::contextoSeguro($e));
        })->stop();

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
