<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Inventario\RegistrarAjuste;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAjusteRequest;
use App\Http\Resources\MovimientoKardexResource;
use Illuminate\Http\JsonResponse;

class AjusteController extends Controller
{
    public function store(StoreAjusteRequest $request, RegistrarAjuste $registrar): JsonResponse
    {
        $movimiento = $registrar->ejecutar(
            $request->usuario(),
            $request->integer('bodega_id'),
            $request->integer('lote_id'),
            $request->integer('cantidad'),
            $request->string('motivo')->trim()->toString(),
        );

        return MovimientoKardexResource::make($movimiento->refresh())->response()->setStatusCode(201);
    }
}
