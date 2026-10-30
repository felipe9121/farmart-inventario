<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bodega;
use App\Models\Existencia;
use App\Support\FechaNegocio;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Lecturas de apoyo para el frontend (selectores de bodega y de lote).
 */
class BodegaController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Bodega::query()->where('activa', true)->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre'])]);
    }

    public function existencias(Bodega $bodega): JsonResponse
    {
        Gate::authorize('ver-alertas');

        $hoy = FechaNegocio::hoy();

        $existencias = Existencia::query()
            ->with(['lote', 'producto'])
            ->where('bodega_id', $bodega->id)
            ->where('cantidad', '>', 0)
            ->get()
            ->sortBy(fn (Existencia $e) => [$e->producto->nombre, $e->lote->fecha_vencimiento->toDateString()])
            ->map(fn (Existencia $e) => [
                'id' => $e->id,
                'cantidad' => $e->cantidad,
                'producto' => ['id' => $e->producto_id, 'nombre' => $e->producto->nombre, 'es_control_especial' => $e->producto->es_control_especial],
                'lote' => [
                    'id' => $e->lote_id,
                    'numero_lote' => $e->lote->numero_lote,
                    'fecha_vencimiento' => $e->lote->fecha_vencimiento->toDateString(),
                    'vencido' => $e->lote->estaVencido($hoy),
                ],
            ])
            ->values();

        return response()->json(['data' => $existencias]);
    }
}
