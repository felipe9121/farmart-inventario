<?php

declare(strict_types=1);

use App\Models\Bodega;
use App\Models\Existencia;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\StockMinimo;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('crea una existencia coherente con el producto del lote', function () {
    $existencia = Existencia::factory()->create();

    expect($existencia->producto_id)->toBe($existencia->lote->producto_id);
});

it('rechaza stock negativo en la BD (RN-03)', function () {
    $existencia = Existencia::factory()->create(['cantidad' => 5]);

    DB::table('existencias')->where('id', $existencia->id)->update(['cantidad' => -1]);
})->throws(QueryException::class, 'ck_existencias_cantidad');

it('rechaza una existencia cuyo producto no es el del lote (RN-01)', function () {
    $lote = Lote::factory()->create();
    $otroProducto = Producto::factory()->create();

    Existencia::factory()->create(['lote_id' => $lote->id, 'producto_id' => $otroProducto->id]);
})->throws(QueryException::class, 'fk_existencias_lote_producto');

it('no duplica la existencia de un lote en la misma bodega', function () {
    $bodega = Bodega::factory()->create();
    $lote = Lote::factory()->create();

    Existencia::factory()->create(['bodega_id' => $bodega->id, 'lote_id' => $lote->id]);
    Existencia::factory()->create(['bodega_id' => $bodega->id, 'lote_id' => $lote->id]);
})->throws(QueryException::class, 'uq_existencias_bodega_lote');

it('permite el mismo lote en bodegas distintas', function () {
    $lote = Lote::factory()->create();

    Existencia::factory()->count(2)->create(['lote_id' => $lote->id]);

    expect($lote->existencias()->count())->toBe(2);
});

it('define un único mínimo por bodega y producto (RN-11)', function () {
    $bodega = Bodega::factory()->create();
    $producto = Producto::factory()->create();

    StockMinimo::factory()->create(['bodega_id' => $bodega->id, 'producto_id' => $producto->id]);
    StockMinimo::factory()->create(['bodega_id' => $bodega->id, 'producto_id' => $producto->id]);
})->throws(QueryException::class, 'uq_stock_minimos_bodega_producto');

it('rechaza un mínimo negativo', function () {
    StockMinimo::factory()->create(['cantidad_minima' => -1]);
})->throws(QueryException::class, 'ck_stock_minimos_cantidad');
