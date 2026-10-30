<?php

declare(strict_types=1);

use App\Actions\Traslados\AnularTraslado;
use App\Actions\Traslados\AprobarTraslado;
use App\Actions\Traslados\CrearTraslado;
use App\Actions\Traslados\DatosTraslado;
use App\Actions\Traslados\SolicitarTraslado;
use App\Enums\EstadoTraslado;
use App\Models\Bodega;
use App\Models\MovimientoKardex;
use Tests\Support\EscenarioTraslado;

it('crea el traslado en BORRADOR con un ítem por lote, sin mover stock', function () {
    $e = EscenarioTraslado::crear();
    $existencia = $e->stockEnOrigen(20);

    $traslado = $e->borrador([[$existencia, 5]]);

    expect($traslado->estado)->toBe(EstadoTraslado::Borrador)
        ->and($traslado->codigo)->toStartWith('TR-')
        ->and($traslado->creado_por)->toBe($e->auxiliar->id)
        ->and($traslado->items()->sole()->cantidad_solicitada)->toBe(5)
        ->and($existencia->refresh()->cantidad)->toBe(20)
        ->and(MovimientoKardex::query()->count())->toBe(0);
});

it('no traslada lotes vencidos (RN-01)', function () {
    $e = EscenarioTraslado::crear();
    $vencido = $e->stockEnOrigen(20, diasParaVencer: -1);

    expect(capturarError(fn () => $e->borrador([[$vencido, 5]]))->codigo)->toBe('LOTE_VENCIDO');
});

it('avisa al crear si el origen no tiene stock suficiente', function () {
    $e = EscenarioTraslado::crear();
    $existencia = $e->stockEnOrigen(3);

    $error = capturarError(fn () => $e->borrador([[$existencia, 5]]));

    expect($error->codigo)->toBe('STOCK_INSUFICIENTE')
        ->and($error->detalle)->toMatchArray(['solicitado' => 5, 'disponible' => 3]);
});

it('rechaza origen y destino iguales y bodegas inactivas', function () {
    $e = EscenarioTraslado::crear();
    $existencia = $e->stockEnOrigen(10);
    $item = [['lote_id' => $existencia->lote_id, 'cantidad' => 1]];
    $inactiva = Bodega::factory()->create(['activa' => false]);

    $iguales = capturarError(fn () => app(CrearTraslado::class)->ejecutar($e->auxiliar, new DatosTraslado($e->origen->id, $e->origen->id, $item)));
    $sinBodega = capturarError(fn () => app(CrearTraslado::class)->ejecutar($e->auxiliar, new DatosTraslado($e->origen->id, $inactiva->id, $item)));

    expect($iguales->codigo)->toBe('BODEGAS_IGUALES')
        ->and($sinBodega->codigo)->toBe('BODEGA_NO_DISPONIBLE');
});

it('solicita y aprueba registrando quién y cuándo', function () {
    $e = EscenarioTraslado::crear();
    $traslado = $e->aprobado([[$e->stockEnOrigen(10), 5]]);

    expect($traslado->estado)->toBe(EstadoTraslado::Aprobado)
        ->and($traslado->solicitado_por)->toBe($e->auxiliar->id)
        ->and($traslado->aprobado_por)->toBe($e->regente->id)
        ->and($traslado->solicitado_at)->not->toBeNull()
        ->and($traslado->aprobado_at)->not->toBeNull();
});

it('impide que quien solicita apruebe (RN-08)', function () {
    $e = EscenarioTraslado::crear();
    $traslado = app(SolicitarTraslado::class)->ejecutar($e->regente, $e->borrador([[$e->stockEnOrigen(10), 5]]));

    $error = capturarError(fn () => app(AprobarTraslado::class)->ejecutar($e->regente, $traslado));

    expect($error->codigo)->toBe('APROBADOR_ES_SOLICITANTE')
        ->and($error->status)->toBe(403)
        ->and($traslado->refresh()->estado)->toBe(EstadoTraslado::Solicitado);
});

it('responde 409 ante una transición inválida', function () {
    $e = EscenarioTraslado::crear();
    $borrador = $e->borrador([[$e->stockEnOrigen(10), 5]]);

    $error = capturarError(fn () => app(AprobarTraslado::class)->ejecutar($e->regente, $borrador));

    expect($error->codigo)->toBe('TRANSICION_INVALIDA')
        ->and($error->status)->toBe(409)
        ->and($error->detalle)->toBe(['estado_actual' => 'BORRADOR', 'estado_destino' => 'APROBADO']);
});

it('no aprueba dos veces el mismo traslado', function () {
    $e = EscenarioTraslado::crear();
    $traslado = $e->aprobado([[$e->stockEnOrigen(10), 5]]);

    expect(capturarError(fn () => app(AprobarTraslado::class)->ejecutar($e->regente, $traslado))->codigo)->toBe('TRANSICION_INVALIDA');
});

it('anula con motivo antes del despacho', function (string $estado) {
    $e = EscenarioTraslado::crear();
    $items = [[$e->stockEnOrigen(10), 5]];
    $traslado = $estado === 'aprobado' ? $e->aprobado($items) : $e->borrador($items);

    $anulado = app(AnularTraslado::class)->ejecutar($e->regente, $traslado, 'Ya no se necesita');

    expect($anulado->estado)->toBe(EstadoTraslado::Anulado)
        ->and($anulado->motivo_anulacion)->toBe('Ya no se necesita')
        ->and($anulado->anulado_por)->toBe($e->regente->id);
})->with(['borrador', 'aprobado']);

it('no reabre un traslado anulado', function () {
    $e = EscenarioTraslado::crear();
    $traslado = app(AnularTraslado::class)->ejecutar($e->regente, $e->borrador([[$e->stockEnOrigen(10), 5]]), 'x');

    expect(capturarError(fn () => app(SolicitarTraslado::class)->ejecutar($e->auxiliar, $traslado))->codigo)->toBe('TRANSICION_INVALIDA');
});
