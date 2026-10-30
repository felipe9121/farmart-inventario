<?php

declare(strict_types=1);

use App\Enums\TipoMovimiento;
use App\Models\Bodega;
use App\Models\MovimientoKardex;
use App\Models\Producto;
use App\Models\TrasladoItem;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('registra una entrada con fecha generada por la BD', function () {
    $movimiento = MovimientoKardex::factory()->create();

    expect($movimiento->fresh()->created_at)->not->toBeNull();
});

it('impide actualizar movimientos en la BD (RN-06)', function () {
    $movimiento = MovimientoKardex::factory()->create();

    DB::table('movimientos_kardex')->where('id', $movimiento->id)->update(['cantidad' => 1]);
})->throws(QueryException::class, 'movimientos_kardex es inmutable');

it('impide eliminar movimientos en la BD (RN-06)', function () {
    $movimiento = MovimientoKardex::factory()->create();

    DB::table('movimientos_kardex')->where('id', $movimiento->id)->delete();
})->throws(QueryException::class, 'no se permite eliminar movimientos');

it('revierte la transacción completa cuando se intenta editar el kardex', function () {
    $movimiento = MovimientoKardex::factory()->create();
    $bodegasAntes = Bodega::query()->count();

    try {
        DB::table('movimientos_kardex')->where('id', $movimiento->id)->update(['cantidad' => 1]);
    } catch (QueryException) {
        // Esperado: el THROW del trigger aborta la transacción en el servidor.
    }

    expect($bodegasAntes)->toBe(1)
        ->and(Bodega::query()->count())->toBe(0)
        ->and(DB::table('movimientos_kardex')->count())->toBe(0);
});

it('falla en el modelo antes de llegar a la BD', function () {
    $movimiento = MovimientoKardex::factory()->create();

    $movimiento->update(['cantidad' => 1]);
})->throws(LogicException::class, 'inmutables');

it('exige motivo en los ajustes', function () {
    MovimientoKardex::factory()->create([
        'tipo' => TipoMovimiento::AjusteNegativo, 'cantidad' => -1, 'saldo_resultante' => 0, 'motivo' => null,
    ]);
})->throws(QueryException::class, 'ck_movimientos_kardex_motivo_ajuste');

it('acepta un ajuste con motivo', function () {
    $movimiento = MovimientoKardex::factory()->create([
        'tipo' => TipoMovimiento::AjusteNegativo, 'cantidad' => -2, 'saldo_resultante' => 3, 'motivo' => 'Avería en estantería',
    ]);

    expect($movimiento->tipo->esAjuste())->toBeTrue();
});

it('exige el signo correcto según el tipo', function () {
    MovimientoKardex::factory()->create(['tipo' => TipoMovimiento::Entrada, 'cantidad' => -5]);
})->throws(QueryException::class, 'ck_movimientos_kardex_signo');

it('rechaza un saldo resultante negativo', function () {
    MovimientoKardex::factory()->create(['saldo_resultante' => -1]);
})->throws(QueryException::class, 'ck_movimientos_kardex_saldo');

it('exige la referencia al documento según el tipo', function () {
    MovimientoKardex::factory()->create(['tipo' => TipoMovimiento::Dispensacion, 'cantidad' => -1]);
})->throws(QueryException::class, 'ck_movimientos_kardex_referencia');

it('rechaza un movimiento cuyo producto no es el del lote', function () {
    MovimientoKardex::factory()->create(['producto_id' => Producto::factory()]);
})->throws(QueryException::class, 'fk_movimientos_kardex_lote_producto');

it('no permite despachar dos veces el mismo ítem de traslado', function () {
    $item = TrasladoItem::factory()->create();
    $datos = [
        'lote_id' => $item->lote_id, 'tipo' => TipoMovimiento::TrasladoSalida,
        'cantidad' => -1, 'saldo_resultante' => 0, 'traslado_item_id' => $item->id, 'documento_referencia' => null,
    ];

    MovimientoKardex::factory()->create($datos);
    MovimientoKardex::factory()->create($datos);
})->throws(QueryException::class, 'uq_movimientos_kardex_traslado_item_tipo');
