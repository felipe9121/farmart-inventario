<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Las respuestas de la API son por usuario y pueden llevar datos de pacientes (RN-10):
 * ni el navegador ni proxies intermedios deben guardarlas.
 */
class SinCache
{
    public function handle(Request $request, Closure $next): Response
    {
        $respuesta = $next($request);

        $respuesta->headers->set('Cache-Control', 'no-store, private');
        $respuesta->headers->set('Pragma', 'no-cache');
        $respuesta->headers->set('X-Content-Type-Options', 'nosniff');

        return $respuesta;
    }
}
