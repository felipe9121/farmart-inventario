<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Traslados\AnularTraslado;
use App\Actions\Traslados\AprobarTraslado;
use App\Actions\Traslados\CrearTraslado;
use App\Actions\Traslados\DespacharTraslado;
use App\Actions\Traslados\RecibirTraslado;
use App\Actions\Traslados\SolicitarTraslado;
use App\Enums\EstadoTraslado;
use App\Http\Controllers\Controller;
use App\Http\Requests\AnularTrasladoRequest;
use App\Http\Requests\DespacharTrasladoRequest;
use App\Http\Requests\RecibirTrasladoRequest;
use App\Http\Requests\StoreTrasladoRequest;
use App\Http\Requests\TransicionTrasladoRequest;
use App\Http\Resources\TrasladoResource;
use App\Models\Traslado;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TrasladoController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Traslado::class);

        $filtros = $request->validate([
            'estado' => ['sometimes', Rule::enum(EstadoTraslado::class)],
            'bodega_id' => ['sometimes', 'integer', 'min:1'],
        ]);

        $traslados = Traslado::query()
            ->when($filtros['estado'] ?? null, fn ($q, $estado) => $q->where('estado', $estado))
            ->when($filtros['bodega_id'] ?? null, fn ($q, $bodega) => $q->where(
                fn ($q) => $q->where('bodega_origen_id', $bodega)->orWhere('bodega_destino_id', $bodega),
            ))
            ->latest('id')
            ->paginate(20);

        return TrasladoResource::collection($traslados);
    }

    public function show(Traslado $traslado): TrasladoResource
    {
        Gate::authorize('view', $traslado);

        return $this->recurso($traslado);
    }

    public function store(StoreTrasladoRequest $request, CrearTraslado $crear): JsonResponse
    {
        return $this->recurso($crear->ejecutar($request->usuario(), $request->datos()))->response()->setStatusCode(201);
    }

    public function solicitar(TransicionTrasladoRequest $request, Traslado $traslado, SolicitarTraslado $solicitar): TrasladoResource
    {
        return $this->recurso($solicitar->ejecutar($request->usuario(), $traslado));
    }

    public function aprobar(TransicionTrasladoRequest $request, Traslado $traslado, AprobarTraslado $aprobar): TrasladoResource
    {
        return $this->recurso($aprobar->ejecutar($request->usuario(), $traslado));
    }

    public function despachar(DespacharTrasladoRequest $request, Traslado $traslado, DespacharTraslado $despachar): TrasladoResource
    {
        return $this->recurso($despachar->ejecutar($request->usuario(), $traslado, $request->cantidades()));
    }

    public function recibir(RecibirTrasladoRequest $request, Traslado $traslado, RecibirTraslado $recibir): TrasladoResource
    {
        return $this->recurso($recibir->ejecutar($request->usuario(), $traslado, $request->recibidas()));
    }

    public function anular(AnularTrasladoRequest $request, Traslado $traslado, AnularTraslado $anular): TrasladoResource
    {
        return $this->recurso($anular->ejecutar($request->usuario(), $traslado, $request->string('motivo')->trim()->toString()));
    }

    private function recurso(Traslado $traslado): TrasladoResource
    {
        return TrasladoResource::make($traslado->refresh()->load('items.lote'));
    }
}
