<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\AccionBitacora;
use App\Http\Controllers\Controller;
use App\Http\Resources\BitacoraAccesoResource;
use App\Models\BitacoraAccesoPaciente;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class BitacoraAccesoController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', BitacoraAccesoPaciente::class);

        $filtros = $request->validate([
            'paciente_id' => ['sometimes', 'integer', 'min:1'],
            'usuario_id' => ['sometimes', 'integer', 'min:1'],
            'accion' => ['sometimes', Rule::enum(AccionBitacora::class)],
            'desde' => ['sometimes', 'date_format:Y-m-d'],
            'hasta' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ]);

        $registros = BitacoraAccesoPaciente::query()
            ->when($filtros['paciente_id'] ?? null, fn ($q, $v) => $q->where('paciente_id', $v))
            ->when($filtros['usuario_id'] ?? null, fn ($q, $v) => $q->where('usuario_id', $v))
            ->when($filtros['accion'] ?? null, fn ($q, $v) => $q->where('accion', $v))
            ->when($filtros['desde'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filtros['hasta'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->orderByDesc('id')
            ->paginate(50);

        return BitacoraAccesoResource::collection($registros);
    }
}
