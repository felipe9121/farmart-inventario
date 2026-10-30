<?php

declare(strict_types=1);

use App\Enums\Rol;
use App\Models\Bodega;
use App\Models\Existencia;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\StockMinimo;
use App\Models\User;
use App\Services\Alertas\AlertasInventario;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

function stockEn(Bodega $bodega, Producto $producto, int $diasParaVencer, int $cantidad): Existencia
{
    $lote = Lote::factory()->venceEnDias($diasParaVencer)->create(['producto_id' => $producto->id]);

    return Existencia::factory()->create([
        'bodega_id' => $bodega->id, 'lote_id' => $lote->id, 'producto_id' => $producto->id, 'cantidad' => $cantidad,
    ]);
}

it('lista los lotes con stock que vencen en 90 días o menos, incluidos los vencidos (RN-11)', function () {
    $bodega = Bodega::factory()->create();
    $producto = Producto::factory()->create();
    $vencido = stockEn($bodega, $producto, -3, 5);
    $limite = stockEn($bodega, $producto, 90, 5);
    stockEn($bodega, $producto, 91, 5);
    stockEn($bodega, $producto, 10, 0);

    $alertas = app(AlertasInventario::class)->lotesPorVencer();

    expect(array_column($alertas, 'lote_id'))->toBe([$vencido->lote_id, $limite->lote_id])
        ->and(array_column($alertas, 'dias_para_vencer'))->toBe([-3, 90])
        ->and($alertas[0])->toMatchArray(['bodega_id' => $bodega->id, 'producto' => $producto->nombre, 'cantidad' => 5]);
});

it('filtra por bodega y por número de días', function () {
    $a = Bodega::factory()->create();
    $b = Bodega::factory()->create();
    $producto = Producto::factory()->create();
    $pronto = stockEn($a, $producto, 10, 5);
    stockEn($a, $producto, 60, 5);
    stockEn($b, $producto, 10, 5);

    expect(array_column(app(AlertasInventario::class)->lotesPorVencer($a->id, 30), 'lote_id'))->toBe([$pronto->lote_id]);
});

it('detecta stock bajo el mínimo sin contar lotes vencidos (RN-11)', function () {
    $bodega = Bodega::factory()->create();
    $bajo = Producto::factory()->create();
    $suficiente = Producto::factory()->create();
    $sinStock = Producto::factory()->create();
    stockEn($bodega, $bajo, 100, 8);
    stockEn($bodega, $bajo, -1, 500);
    stockEn($bodega, $suficiente, 100, 30);
    foreach ([[$bajo, 20], [$suficiente, 30], [$sinStock, 5]] as [$producto, $minimo]) {
        StockMinimo::factory()->create(['bodega_id' => $bodega->id, 'producto_id' => $producto->id, 'cantidad_minima' => $minimo]);
    }

    $alertas = collect(app(AlertasInventario::class)->stockBajoMinimo($bodega->id))->keyBy('producto_id');

    expect($alertas->keys()->sort()->values()->all())->toBe(collect([$bajo->id, $sinStock->id])->sort()->values()->all())
        ->and($alertas[$bajo->id])->toMatchArray(['minimo' => 20, 'disponible' => 8, 'faltante' => 12])
        ->and($alertas[$sinStock->id])->toMatchArray(['disponible' => 0, 'faltante' => 5]);
});

it('expone las alertas por la API a los roles de inventario', function () {
    $bodega = Bodega::factory()->create();
    stockEn($bodega, Producto::factory()->create(), 5, 3);
    Sanctum::actingAs(User::factory()->rol(Rol::Auditor)->create());

    getJson("/api/alertas/vencimientos?bodega_id={$bodega->id}")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.dias_para_vencer', 5);
    getJson('/api/alertas/stock-bajo')->assertOk()->assertJsonCount(0, 'data');
    getJson('/api/alertas/vencimientos?dias=0')->assertUnprocessable();
});

it('el médico no ve las alertas de inventario', function () {
    Sanctum::actingAs(User::factory()->rol(Rol::Medico)->create());

    getJson('/api/alertas/vencimientos')->assertForbidden();
});
