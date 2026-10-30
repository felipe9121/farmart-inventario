<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Dispensaciones\RegistrarDispensacion;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDispensacionRequest;
use App\Http\Resources\DispensacionResource;
use App\Models\Dispensacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class DispensacionController extends Controller
{
    public function store(StoreDispensacionRequest $request, RegistrarDispensacion $registrar): JsonResponse
    {
        $resultado = $registrar->ejecutar($request->usuario(), $request->datos(), $request->llaveIdempotencia());

        return DispensacionResource::make($resultado->dispensacion->load('items.lote'))
            ->response()
            ->setStatusCode($resultado->repetida ? Response::HTTP_OK : Response::HTTP_CREATED)
            ->header('Idempotent-Replayed', $resultado->repetida ? 'true' : 'false');
    }

    public function show(Dispensacion $dispensacion): DispensacionResource
    {
        Gate::authorize('view', $dispensacion);

        return DispensacionResource::make($dispensacion->load('items.lote'));
    }
}
