<?php

declare(strict_types=1);

use App\Enums\EstadoTraslado;
use App\Enums\Rol;
use App\Models\Bodega;
use App\Models\Traslado;
use App\Models\TrasladoItem;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('crea traslados en BORRADOR por defecto', function () {
    $traslado = Traslado::factory()->create();

    expect($traslado->fresh()->estado)->toBe(EstadoTraslado::Borrador);
});

it('rechaza un estado inexistente', function () {
    $traslado = Traslado::factory()->create();

    DB::table('traslados')->where('id', $traslado->id)->update(['estado' => 'PERDIDO']);
})->throws(QueryException::class, 'ck_traslados_estado');

it('rechaza origen y destino iguales', function () {
    $bodega = Bodega::factory()->create();

    Traslado::factory()->create(['bodega_origen_id' => $bodega->id, 'bodega_destino_id' => $bodega->id]);
})->throws(QueryException::class, 'ck_traslados_bodegas_distintas');

it('impide en la BD que el solicitante apruebe su traslado (RN-08)', function () {
    $usuario = User::factory()->rol(Rol::RegenteFarmacia)->create();

    Traslado::factory()->solicitado($usuario)->create([
        'estado' => EstadoTraslado::Aprobado, 'aprobado_por' => $usuario->id, 'aprobado_at' => now(),
    ]);
})->throws(QueryException::class, 'ck_traslados_aprobador_distinto');

it('acepta la aprobación de un usuario distinto al solicitante', function () {
    $traslado = Traslado::factory()->aprobado()->create();

    expect($traslado->aprobado_por)->not->toBe($traslado->solicitado_por);
});

it('no permite un estado APROBADO sin aprobador (RN-07)', function () {
    Traslado::factory()->solicitado()->create(['estado' => EstadoTraslado::Aprobado]);
})->throws(QueryException::class, 'ck_traslados_estado_coherente');

it('no permite EN_TRANSITO sin despacho', function () {
    Traslado::factory()->aprobado()->create(['estado' => EstadoTraslado::EnTransito]);
})->throws(QueryException::class, 'ck_traslados_estado_coherente');

it('exige motivo para anular', function () {
    $traslado = Traslado::factory()->create();

    DB::table('traslados')->where('id', $traslado->id)->update([
        'estado' => 'ANULADO', 'anulado_por' => $traslado->creado_por, 'anulado_at' => now(),
    ]);
})->throws(QueryException::class, 'ck_traslados_anulacion');

it('no permite anular un traslado ya despachado', function () {
    $traslado = Traslado::factory()->aprobado()->create();
    DB::table('traslados')->where('id', $traslado->id)->update([
        'estado' => 'EN_TRANSITO', 'despachado_por' => $traslado->aprobado_por, 'despachado_at' => now(),
    ]);

    DB::table('traslados')->where('id', $traslado->id)->update([
        'estado' => 'ANULADO', 'anulado_por' => $traslado->creado_por, 'anulado_at' => now(), 'motivo_anulacion' => 'x',
    ]);
})->throws(QueryException::class, 'ck_traslados_anulacion');

it('calcula la discrepancia entre lo despachado y lo recibido', function () {
    $item = TrasladoItem::factory()->create(['cantidad_solicitada' => 20]);

    DB::table('traslado_items')->where('id', $item->id)->update(['cantidad_despachada' => 20, 'cantidad_recibida' => 15]);

    expect($item->fresh()->cantidad_discrepancia)->toBe(5);
});

it('no permite despachar más de lo solicitado', function () {
    $item = TrasladoItem::factory()->create(['cantidad_solicitada' => 10]);

    DB::table('traslado_items')->where('id', $item->id)->update(['cantidad_despachada' => 11]);
})->throws(QueryException::class, 'ck_traslado_items_cantidad_despachada');

it('no permite recibir más de lo despachado', function () {
    $item = TrasladoItem::factory()->create(['cantidad_solicitada' => 10]);

    DB::table('traslado_items')->where('id', $item->id)->update(['cantidad_despachada' => 8, 'cantidad_recibida' => 9]);
})->throws(QueryException::class, 'ck_traslado_items_cantidad_recibida');

it('no permite recibir sin despacho', function () {
    $item = TrasladoItem::factory()->create();

    DB::table('traslado_items')->where('id', $item->id)->update(['cantidad_recibida' => 1]);
})->throws(QueryException::class, 'ck_traslado_items_cantidad_recibida');
