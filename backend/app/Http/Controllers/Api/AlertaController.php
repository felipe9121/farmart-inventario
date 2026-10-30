<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Alertas\AlertasInventario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AlertaController extends Controller
{
    public function __construct(private readonly AlertasInventario $alertas) {}

    public function vencimientos(Request $request): JsonResponse
    {
        Gate::authorize('ver-alertas');

        $filtros = $request->validate([
            'bodega_id' => ['sometimes', 'integer', 'min:1'],
            'dias' => ['sometimes', 'integer', 'between:1,365'],
        ]);

        return response()->json(['data' => $this->alertas->lotesPorVencer(
            isset($filtros['bodega_id']) ? (int) $filtros['bodega_id'] : null,
            (int) ($filtros['dias'] ?? AlertasInventario::DIAS_VENCIMIENTO),
        )]);
    }

    public function stockBajo(Request $request): JsonResponse
    {
        Gate::authorize('ver-alertas');

        $filtros = $request->validate(['bodega_id' => ['sometimes', 'integer', 'min:1']]);

        return response()->json(['data' => $this->alertas->stockBajoMinimo(
            isset($filtros['bodega_id']) ? (int) $filtros['bodega_id'] : null,
        )]);
    }
}
