<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Asistente\Asistente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AsistenteController extends Controller
{
    public function preguntar(Request $request, Asistente $asistente): JsonResponse
    {
        Gate::authorize('ver-alertas');

        $pregunta = $request->validate(['pregunta' => ['required', 'string', 'min:3', 'max:300']])['pregunta'];
        $respuesta = $asistente->responder((string) $pregunta);

        return response()->json(['data' => [
            'intencion' => $respuesta->intencion->value,
            'respuesta' => $respuesta->texto,
            'filtros' => [
                'bodega_id' => $respuesta->interpretacion->bodegaId,
                'dias' => $respuesta->interpretacion->dias,
            ],
            'datos' => $respuesta->datos,
            'sugerencias' => $respuesta->sugerencias,
            'modelo' => $respuesta->modelo,
        ]]);
    }
}
