<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * RN-09: exige el header Idempotency-Key (draft-ietf-httpapi-idempotency-key-header):
 * 400 si falta o no es válido. Se aceptan 1..100 caracteres ASCII imprimibles (p. ej. un UUID).
 */
class RequiereIdempotencyKey
{
    public const HEADER = 'Idempotency-Key';

    public function handle(Request $request, Closure $next): Response
    {
        $llave = $request->header(self::HEADER);

        if (! is_string($llave) || preg_match('/^[\x21-\x7E]{1,100}$/', $llave) !== 1) {
            return response()->json([
                'message' => 'El header Idempotency-Key es obligatorio (1 a 100 caracteres ASCII imprimibles).',
                'codigo' => 'IDEMPOTENCY_KEY_REQUERIDA',
            ], Response::HTTP_BAD_REQUEST);
        }

        return $next($request);
    }
}
