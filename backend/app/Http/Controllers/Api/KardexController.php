<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\TipoMovimiento;
use App\Http\Controllers\Controller;
use App\Http\Resources\MovimientoKardexResource;
use App\Models\MovimientoKardex;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class KardexController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', MovimientoKardex::class);

        $filtros = $request->validate([
            'bodega_id' => ['sometimes', 'integer', 'min:1'],
            'producto_id' => ['sometimes', 'integer', 'min:1'],
            'lote_id' => ['sometimes', 'integer', 'min:1'],
            'tipo' => ['sometimes', Rule::enum(TipoMovimiento::class)],
            'desde' => ['sometimes', 'date_format:Y-m-d'],
            'hasta' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'por_pagina' => ['sometimes', 'integer', 'between:1,100'],
        ]);

        $movimientos = MovimientoKardex::query()
            ->when($filtros['bodega_id'] ?? null, fn ($q, $v) => $q->where('bodega_id', $v))
            ->when($filtros['producto_id'] ?? null, fn ($q, $v) => $q->where('producto_id', $v))
            ->when($filtros['lote_id'] ?? null, fn ($q, $v) => $q->where('lote_id', $v))
            ->when($filtros['tipo'] ?? null, fn ($q, $v) => $q->where('tipo', $v))
            ->when($filtros['desde'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filtros['hasta'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            // El id es el orden real de registro (created_at puede coincidir al milisegundo).
            ->orderByDesc('id')
            ->paginate((int) ($filtros['por_pagina'] ?? 50));

        return MovimientoKardexResource::collection($movimientos);
    }
}
