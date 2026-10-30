<?php

declare(strict_types=1);

use App\Actions\Traslados\DespacharTraslado;
use App\Actions\Traslados\RecibirTraslado;
use App\Enums\EstadoTraslado;
use App\Enums\TipoMovimiento;
use App\Models\Existencia;
use App\Models\Lote;
use App\Models\MovimientoKardex;
use App\Models\Traslado;
use App\Models\TrasladoItem;
use Illuminate\Support\Facades\DB;
use Tests\Support\EscenarioTraslado;

/**
 * @param  array<int, int>  $cantidades
 */
function despachar(EscenarioTraslado $e, Traslado $traslado, array $cantidades = []): Traslado
{
    return app(DespacharTraslado::class)->ejecutar($e->auxiliar, $traslado, $cantidades);
}

/**
 * @param  array<int, int>  $recibidas
 */
function recibir(EscenarioTraslado $e, Traslado $traslado, array $recibidas): Traslado
{
    return app(RecibirTraslado::class)->ejecutar($e->auxiliar, $traslado, $recibidas);
}

function existenciaEnDestino(EscenarioTraslado $e, int $loteId): ?Existencia
{
    return Existencia::query()->where('bodega_id', $e->destino->id)->where('lote_id', $loteId)->first();
}

/** Cada existencia debe ser igual a la suma de su kardex. */
function kardexCuadra(): bool
{
    return DB::table('existencias as e')
        ->leftJoin(
            DB::raw('(SELECT bodega_id, lote_id, SUM(cantidad) AS total FROM movimientos_kardex GROUP BY bodega_id, lote_id) k'),
            fn ($join) => $join->on('k.bodega_id', '=', 'e.bodega_id')->on('k.lote_id', '=', 'e.lote_id'),
        )
        ->whereRaw('COALESCE(k.total, 0) <> e.cantidad')
        ->doesntExist();
}

/**
 * Stock inicial con su ENTRADA en el kardex, para poder verificar que cuadre.
 */
function stockConKardex(EscenarioTraslado $e, int $cantidad): Existencia
{
    $existencia = $e->stockEnOrigen($cantidad);
    MovimientoKardex::factory()->create([
        'bodega_id' => $existencia->bodega_id, 'lote_id' => $existencia->lote_id, 'producto_id' => $existencia->producto_id,
        'cantidad' => $cantidad, 'saldo_resultante' => $cantidad, 'usuario_id' => $e->auxiliar->id,
    ]);

    return $existencia;
}

it('saca el stock del origen al despachar (RN-07)', function () {
    $e = EscenarioTraslado::crear();
    $existencia = stockConKardex($e, 20);
    $traslado = $e->aprobado([[$existencia, 8]]);

    $despachado = despachar($e, $traslado);

    $salida = MovimientoKardex::query()->where('tipo', TipoMovimiento::TrasladoSalida)->sole();
    expect($despachado->estado)->toBe(EstadoTraslado::EnTransito)
        ->and($despachado->despachado_por)->toBe($e->auxiliar->id)
        ->and($existencia->refresh()->cantidad)->toBe(12)
        ->and($salida->cantidad)->toBe(-8)
        ->and($salida->traslado_item_id)->toBe($traslado->items()->sole()->id)
        ->and(existenciaEnDestino($e, $existencia->lote_id))->toBeNull()
        ->and(kardexCuadra())->toBeTrue();
});

it('permite despachar menos de lo solicitado por ítem', function () {
    $e = EscenarioTraslado::crear();
    $a = $e->stockEnOrigen(20);
    $b = $e->stockEnOrigen(20);
    $traslado = $e->aprobado([[$a, 8], [$b, 5]]);
    $itemA = $traslado->items()->where('lote_id', $a->lote_id)->sole();
    $itemB = $traslado->items()->where('lote_id', $b->lote_id)->sole();

    despachar($e, $traslado, [$itemA->id => 3, $itemB->id => 0]);

    expect($itemA->refresh()->cantidad_despachada)->toBe(3)
        ->and($itemB->refresh()->cantidad_despachada)->toBe(0)
        ->and($a->refresh()->cantidad)->toBe(17)
        ->and($b->refresh()->cantidad)->toBe(20)
        ->and(MovimientoKardex::query()->where('tipo', TipoMovimiento::TrasladoSalida)->count())->toBe(1);
});

it('rechaza despachar más de lo solicitado o un despacho vacío', function () {
    $e = EscenarioTraslado::crear();
    $traslado = $e->aprobado([[$e->stockEnOrigen(20), 8]]);
    $item = $traslado->items()->sole();

    expect(capturarError(fn () => despachar($e, $traslado, [$item->id => 9]))->codigo)->toBe('EXCEDE_LO_SOLICITADO')
        ->and(capturarError(fn () => despachar($e, $traslado, [$item->id => 0]))->codigo)->toBe('DESPACHO_VACIO')
        ->and($traslado->refresh()->estado)->toBe(EstadoTraslado::Aprobado);
});

it('rechaza el despacho completo si falta stock en un ítem, sin mover nada', function () {
    $e = EscenarioTraslado::crear();
    $a = $e->stockEnOrigen(20);
    $b = $e->stockEnOrigen(20);
    $traslado = $e->aprobado([[$a, 8], [$b, 5]]);
    // El stock de B se consumió entre la aprobación y el despacho.
    $b->forceFill(['cantidad' => 2])->save();

    $error = capturarError(fn () => despachar($e, $traslado));

    expect($error->codigo)->toBe('STOCK_INSUFICIENTE')
        ->and($a->refresh()->cantidad)->toBe(20)
        ->and(MovimientoKardex::query()->count())->toBe(0)
        ->and($traslado->refresh()->estado)->toBe(EstadoTraslado::Aprobado);
});

it('no despacha un lote que se venció después de la solicitud (RN-01)', function () {
    $e = EscenarioTraslado::crear();
    $existencia = $e->stockEnOrigen(20);
    $traslado = $e->aprobado([[$existencia, 8]]);
    Lote::query()->whereKey($existencia->lote_id)->update(['fecha_vencimiento' => now()->subDays(2)->toDateString()]);

    expect(capturarError(fn () => despachar($e, $traslado))->codigo)->toBe('LOTE_VENCIDO');
});

it('no despacha un traslado sin aprobar', function () {
    $e = EscenarioTraslado::crear();
    $borrador = $e->borrador([[$e->stockEnOrigen(20), 8]]);

    expect(capturarError(fn () => despachar($e, $borrador))->codigo)->toBe('TRANSICION_INVALIDA');
});

it('entra el stock al destino al recibir todo (RECIBIDO)', function () {
    $e = EscenarioTraslado::crear();
    $existencia = stockConKardex($e, 20);
    $traslado = despachar($e, $e->aprobado([[$existencia, 8]]));
    $item = $traslado->items()->sole();

    $recibido = recibir($e, $traslado, [$item->id => 8]);

    $destino = existenciaEnDestino($e, $existencia->lote_id);
    $entrada = MovimientoKardex::query()->where('tipo', TipoMovimiento::TrasladoEntrada)->sole();
    expect($recibido->estado)->toBe(EstadoTraslado::Recibido)
        ->and($destino?->cantidad)->toBe(8)
        ->and($entrada->bodega_id)->toBe($e->destino->id)
        ->and($entrada->cantidad)->toBe(8)
        ->and($item->refresh()->cantidad_discrepancia)->toBe(0)
        ->and(kardexCuadra())->toBeTrue();
});

it('suma a la existencia del destino si el lote ya estaba allí', function () {
    $e = EscenarioTraslado::crear();
    $existencia = $e->stockEnOrigen(20);
    Existencia::factory()->create([
        'bodega_id' => $e->destino->id, 'lote_id' => $existencia->lote_id, 'producto_id' => $existencia->producto_id, 'cantidad' => 5,
    ]);
    $traslado = despachar($e, $e->aprobado([[$existencia, 8]]));

    recibir($e, $traslado, [$traslado->items()->sole()->id => 8]);

    expect(existenciaEnDestino($e, $existencia->lote_id)?->cantidad)->toBe(13);
});

it('deja como discrepancia pendiente lo no recibido (RECIBIDO_PARCIAL)', function () {
    $e = EscenarioTraslado::crear();
    $existencia = stockConKardex($e, 20);
    $traslado = despachar($e, $e->aprobado([[$existencia, 8]]));
    $item = $traslado->items()->sole();

    $recibido = recibir($e, $traslado, [$item->id => 6]);

    expect($recibido->estado)->toBe(EstadoTraslado::RecibidoParcial)
        ->and($item->refresh()->cantidad_discrepancia)->toBe(2)
        ->and(existenciaEnDestino($e, $existencia->lote_id)?->cantidad)->toBe(6)
        // Lo no recibido no vuelve al origen: salió con el despacho.
        ->and($existencia->refresh()->cantidad)->toBe(12)
        ->and(kardexCuadra())->toBeTrue();
});

it('exige informar cada ítem y no recibir más de lo despachado', function () {
    $e = EscenarioTraslado::crear();
    $a = $e->stockEnOrigen(20);
    $b = $e->stockEnOrigen(20);
    $traslado = despachar($e, $e->aprobado([[$a, 8], [$b, 5]]));
    [$itemA, $itemB] = $traslado->items()->orderBy('id')->get()->all();

    expect(capturarError(fn () => recibir($e, $traslado, [$itemA->id => 8]))->codigo)->toBe('RECEPCION_INCOMPLETA')
        ->and(capturarError(fn () => recibir($e, $traslado, [$itemA->id => 9, $itemB->id => 5]))->codigo)->toBe('EXCEDE_LO_DESPACHADO')
        ->and(capturarError(fn () => recibir($e, $traslado, [$itemA->id => 8, $itemB->id => 5, 999999 => 1]))->codigo)->toBe('ITEM_NO_PERTENECE_AL_TRASLADO')
        ->and($traslado->refresh()->estado)->toBe(EstadoTraslado::EnTransito);
});

it('no recibe dos veces el mismo traslado', function () {
    $e = EscenarioTraslado::crear();
    $traslado = despachar($e, $e->aprobado([[$e->stockEnOrigen(20), 8]]));
    $item = $traslado->items()->sole();
    recibir($e, $traslado, [$item->id => 8]);

    expect(capturarError(fn () => recibir($e, $traslado, [$item->id => 8]))->codigo)->toBe('TRANSICION_INVALIDA')
        ->and(MovimientoKardex::query()->where('tipo', TipoMovimiento::TrasladoEntrada)->count())->toBe(1);
});

it('un ítem no despachado se recibe en 0 sin movimiento', function () {
    $e = EscenarioTraslado::crear();
    $a = $e->stockEnOrigen(20);
    $b = $e->stockEnOrigen(20);
    $traslado = $e->aprobado([[$a, 8], [$b, 5]]);
    $itemB = TrasladoItem::query()->where('traslado_id', $traslado->id)->where('lote_id', $b->lote_id)->sole();
    $itemA = TrasladoItem::query()->where('traslado_id', $traslado->id)->where('lote_id', $a->lote_id)->sole();
    despachar($e, $traslado, [$itemB->id => 0]);

    $recibido = recibir($e, $traslado, [$itemA->id => 8, $itemB->id => 0]);

    expect($recibido->estado)->toBe(EstadoTraslado::Recibido)
        ->and(existenciaEnDestino($e, $b->lote_id))->toBeNull();
});
