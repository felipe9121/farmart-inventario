<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Un usuario desactivado pierde el acceso de inmediato, aunque tenga tokens vigentes.
 */
class UsuarioActivo
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario instanceof User && ! $usuario->activo) {
            return response()->json([
                'message' => 'El usuario está inactivo.',
                'codigo' => 'USUARIO_INACTIVO',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
