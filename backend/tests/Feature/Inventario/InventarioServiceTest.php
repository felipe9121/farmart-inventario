<?php

declare(strict_types=1);

use App\Enums\TipoMovimiento;
use App\Exceptions\ReglaNegocioException;
use App\Models\Bodega;
use App\Models\Existencia;
use App\Models\Lote;
use App\Models\MovimientoKardex;
use App\Models\Producto;
use App\Models\TrasladoItem;
use App\Models\User;
use App\Services\Inventario\AsignacionLote;
use App\Services\Inventario\InventarioService;
use Illuminate\Support\Facades\DB;

function existenciaDe(Producto $producto, Bodega $bodega, int $diasParaVencer, int $cantidad): Existencia
{
    $lote = Lote::factory()->for($producto)->venceEnDias($diasParaVencer)->create();

    return Existencia::factory()->create([
        'bodega_id' => $bodega->id, 'lote_id' => $lote->id, 'producto_id' => $producto->id, 'cantidad' => $cantidad,
    ]);
}

/**
 * @return list<array{int, int}> pares [existencia_id, cantidad]
 */
function asignarFefo(Bodega $bodega, Producto $producto, int $cantidad): array
{
    $asignaciones = DB::transaction(fn () => app(InventarioService::class)->asignarFefo($bodega->id, $producto->id, $cantidad));

    return array_map(fn (AsignacionLote $a) => [$a->existencia->id, $a->cantidad], $asignaciones);
}

it('toma primero el lote que vence antes (FEFO)', function () {
    [$bodega, $producto] = [Bodega::factory()->create(), Producto::factory()->create()];
    existenciaDe($producto, $bodega, 200, 50);
    $pronto = existenciaDe($producto, $bodega, 20, 50);

    expect(asignarFefo($bodega, $producto, 10))->toBe([[$pronto->id, 10]]);
});

it('reparte entre varios lotes en orden de vencimiento (RN-02)', function () {
    [$bodega, $producto] = [Bodega::factory()->create(), Producto::factory()->create()];
    $tercero = existenciaDe($producto, $bodega, 300, 100);
    $primero = existenciaDe($producto, $bodega, 10, 4);
    $segundo = existenciaDe($producto, $bodega, 100, 5);

    expect(asignarFefo($bodega, $producto, 12))->toBe([[$primero->id, 4], [$segundo->id, 5], [$tercero->id, 3]]);
});

it('usa un lote que vence hoy pero nunca uno vencido (RN-01)', function () {
    [$bodega, $producto] = [Bodega::factory()->create(), Producto::factory()->create()];
    existenciaDe($producto, $bodega, -1, 100);
    $hoy = existenciaDe($producto, $bodega, 0, 5);

    expect(asignarFefo($bodega, $producto, 5))->toBe([[$hoy->id, 5]]);
});

it('ignora el stock de otras bodegas', function () {
    [$bodega, $producto] = [Bodega::factory()->create(), Producto::factory()->create()];
    existenciaDe($producto, Bodega::factory()->create(), 30, 100);
    existenciaDe($producto, $bodega, 30, 3);

    asignarFefo($bodega, $producto, 4);
})->throws(ReglaNegocioException::class, 'No hay stock vigente suficiente');

it('informa lo disponible cuando no alcanza, sin contar vencidos', function () {
    [$bodega, $producto] = [Bodega::factory()->create(), Producto::factory()->create()];
    existenciaDe($producto, $bodega, -5, 100);
    existenciaDe($producto, $bodega, 30, 7);

    $error = capturarError(fn () => asignarFefo($bodega, $producto, 8));

    expect($error->codigo)->toBe('STOCK_INSUFICIENTE')
        ->and($error->detalle)->toBe(['producto_id' => $producto->id, 'solicitado' => 8, 'disponible' => 7]);
});

it('descuenta stock y registra el movimiento con su saldo (RN-06)', function () {
    $existencia = existenciaDe(Producto::factory()->create(), Bodega::factory()->create(), 30, 10);
    $usuario = User::factory()->create();
    $item = TrasladoItem::factory()->create(['lote_id' => $existencia->lote_id]);

    $movimiento = DB::transaction(fn () => app(InventarioService::class)->registrarSalida(
        $existencia, 4, TipoMovimiento::TrasladoSalida, $usuario, ['traslado_item_id' => $item->id],
    ))->refresh();

    expect($existencia->refresh()->cantidad)->toBe(6)
        ->and($movimiento->cantidad)->toBe(-4)
        ->and($movimiento->saldo_resultante)->toBe(6)
        ->and($movimiento->tipo)->toBe(TipoMovimiento::TrasladoSalida)
        ->and($movimiento->traslado_item_id)->toBe($item->id)
        ->and($movimiento->usuario_id)->toBe($usuario->id);
});

it('no deja el stock en negativo (RN-03)', function () {
    $existencia = existenciaDe(Producto::factory()->create(), Bodega::factory()->create(), 30, 3);

    DB::transaction(fn () => app(InventarioService::class)
        ->registrarSalida($existencia, 4, TipoMovimiento::AjusteNegativo, User::factory()->create(), motivo: 'Avería'));
})->throws(ReglaNegocioException::class, 'no tiene stock suficiente');

it('no registra movimientos de entrada como salida', function () {
    $existencia = existenciaDe(Producto::factory()->create(), Bodega::factory()->create(), 30, 3);

    DB::transaction(fn () => app(InventarioService::class)
        ->registrarSalida($existencia, 1, TipoMovimiento::Entrada, User::factory()->create()));
})->throws(LogicException::class, 'Salida inválida');

it('exige que el cambio de stock ocurra dentro de una transacción', function () {
    // Se sale de la transacción de RefreshDatabase para simular un llamador sin transacción.
    DB::rollBack();

    try {
        app(InventarioService::class)->asignarFefo(1, 1, 1);
    } finally {
        DB::beginTransaction();
    }
})->throws(LogicException::class, 'dentro de una transacción');

it('no deja cambios de stock ni movimientos si la transacción se revierte', function () {
    $existencia = existenciaDe(Producto::factory()->create(), Bodega::factory()->create(), 30, 10);
    $usuario = User::factory()->create();
    $antes = MovimientoKardex::query()->count();

    try {
        DB::transaction(function () use ($existencia, $usuario) {
            app(InventarioService::class)->registrarSalida($existencia, 4, TipoMovimiento::AjusteNegativo, $usuario, motivo: 'Avería');
            throw new RuntimeException('falla posterior');
        });
    } catch (RuntimeException) {
        // Esperado.
    }

    expect($existencia->refresh()->cantidad)->toBe(10)
        ->and(MovimientoKardex::query()->count())->toBe($antes);
});

it('crea la existencia en la bodega destino si no existía y registra la entrada', function () {
    $lote = Lote::factory()->create();
    $bodega = Bodega::factory()->create();
    $item = TrasladoItem::factory()->create(['lote_id' => $lote->id]);

    $movimiento = DB::transaction(fn () => app(InventarioService::class)->registrarEntrada(
        $bodega->id, $lote, 7, TipoMovimiento::TrasladoEntrada, User::factory()->create(), ['traslado_item_id' => $item->id],
    ))->refresh();

    $existencia = Existencia::query()->where('bodega_id', $bodega->id)->where('lote_id', $lote->id)->sole();
    expect($existencia->cantidad)->toBe(7)
        ->and($existencia->producto_id)->toBe($lote->producto_id)
        ->and($movimiento->cantidad)->toBe(7)
        ->and($movimiento->saldo_resultante)->toBe(7);
});

it('suma a la existencia que ya había', function () {
    $existencia = existenciaDe(Producto::factory()->create(), Bodega::factory()->create(), 30, 10);

    $movimiento = DB::transaction(fn () => app(InventarioService::class)->registrarEntrada(
        $existencia->bodega_id, $existencia->lote, 5, TipoMovimiento::AjustePositivo, User::factory()->create(), motivo: 'Conteo físico',
    ));

    expect($existencia->refresh()->cantidad)->toBe(15)
        ->and($movimiento->saldo_resultante)->toBe(15)
        ->and(Existencia::query()->count())->toBe(1);
});

it('no registra salidas como entrada', function () {
    DB::transaction(fn () => app(InventarioService::class)->registrarEntrada(
        Bodega::factory()->create()->id, Lote::factory()->create(), 1, TipoMovimiento::Dispensacion, User::factory()->create(),
    ));
})->throws(LogicException::class, 'Entrada inválida');
