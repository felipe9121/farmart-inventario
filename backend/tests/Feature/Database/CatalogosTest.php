<?php

declare(strict_types=1);

use App\Models\Bodega;
use App\Models\Lote;
use App\Models\Producto;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('no permite códigos de bodega duplicados', function () {
    Bodega::factory()->create(['codigo' => 'BOD-1']);
    Bodega::factory()->create(['codigo' => 'BOD-1']);
})->throws(QueryException::class, 'uq_bodegas_codigo');

it('no permite códigos de producto duplicados', function () {
    Producto::factory()->create(['codigo' => 'MED-1']);
    Producto::factory()->create(['codigo' => 'MED-1']);
})->throws(QueryException::class, 'uq_productos_codigo');

it('no permite el mismo número de lote dos veces para un producto', function () {
    $producto = Producto::factory()->create();
    Lote::factory()->for($producto)->create(['numero_lote' => 'L-1']);
    Lote::factory()->for($producto)->create(['numero_lote' => 'L-1']);
})->throws(QueryException::class, 'uq_lotes_producto_numero');

it('permite el mismo número de lote en productos distintos', function () {
    Lote::factory()->create(['numero_lote' => 'L-1']);
    Lote::factory()->create(['numero_lote' => 'L-1']);

    expect(Lote::query()->where('numero_lote', 'L-1')->count())->toBe(2);
});

it('rechaza un lote de un producto inexistente', function () {
    DB::table('lotes')->insert(['producto_id' => 999_999, 'numero_lote' => 'L-1', 'fecha_vencimiento' => '2030-01-01']);
})->throws(QueryException::class, 'FOREIGN KEY');

it('rechaza códigos vacíos', function () {
    DB::table('bodegas')->insert(['codigo' => '', 'nombre' => 'X']);
})->throws(QueryException::class, 'ck_bodegas_codigo');

it('considera vencido un lote solo después de su fecha de vencimiento', function () {
    $hoy = CarbonImmutable::parse('2026-10-29');
    $lote = Lote::factory()->make(['fecha_vencimiento' => '2026-10-29']);

    expect($lote->estaVencido($hoy))->toBeFalse()
        ->and($lote->estaVencido($hoy->addDay()))->toBeTrue();
});

it('el scope vigentes excluye lotes vencidos', function () {
    $vigente = Lote::factory()->venceEnDias(0)->create();
    Lote::factory()->vencido()->create();

    expect(Lote::query()->vigentes()->pluck('id')->all())->toBe([$vigente->id]);
});
