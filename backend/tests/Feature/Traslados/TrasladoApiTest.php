<?php

declare(strict_types=1);

use App\Enums\Rol;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\Support\EscenarioTraslado;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

it('recorre el flujo completo por la API hasta una recepción parcial', function () {
    $e = EscenarioTraslado::crear();
    $existencia = $e->stockEnOrigen(20);

    Sanctum::actingAs($e->auxiliar);
    $id = postJson('/api/traslados', [
        'bodega_origen_id' => $e->origen->id,
        'bodega_destino_id' => $e->destino->id,
        'items' => [['lote_id' => $existencia->lote_id, 'cantidad' => 8]],
    ])->assertCreated()->assertJsonPath('data.estado', 'BORRADOR')->json('data.id');
    postJson("/api/traslados/{$id}/solicitar")->assertOk()->assertJsonPath('data.estado', 'SOLICITADO');

    Sanctum::actingAs($e->regente);
    postJson("/api/traslados/{$id}/aprobar")->assertOk()->assertJsonPath('data.aprobado_por', $e->regente->id);

    Sanctum::actingAs($e->auxiliar);
    $itemId = postJson("/api/traslados/{$id}/despachar")
        ->assertOk()->assertJsonPath('data.estado', 'EN_TRANSITO')->assertJsonPath('data.items.0.cantidad_despachada', 8)
        ->json('data.items.0.id');
    postJson("/api/traslados/{$id}/recibir", ['items' => [['traslado_item_id' => $itemId, 'cantidad_recibida' => 5]]])
        ->assertOk()
        ->assertJsonPath('data.estado', 'RECIBIDO_PARCIAL')
        ->assertJsonPath('data.items.0.cantidad_discrepancia', 3);

    getJson("/api/traslados/{$id}")->assertOk()->assertJsonPath('data.recibido_por', $e->auxiliar->id);
    expect($existencia->refresh()->cantidad)->toBe(12);
});

it('responde 403 si quien solicita intenta aprobar (RN-08)', function () {
    $e = EscenarioTraslado::crear();
    Sanctum::actingAs($e->regente);
    $id = postJson('/api/traslados', [
        'bodega_origen_id' => $e->origen->id, 'bodega_destino_id' => $e->destino->id,
        'items' => [['lote_id' => $e->stockEnOrigen(20)->lote_id, 'cantidad' => 1]],
    ])->json('data.id');
    postJson("/api/traslados/{$id}/solicitar")->assertOk();

    postJson("/api/traslados/{$id}/aprobar")
        ->assertForbidden()
        ->assertJsonPath('codigo', 'APROBADOR_ES_SOLICITANTE');
});

it('responde 409 ante una transición inválida', function () {
    $e = EscenarioTraslado::crear();
    $traslado = $e->borrador([[$e->stockEnOrigen(20), 1]]);
    Sanctum::actingAs($e->auxiliar);

    postJson("/api/traslados/{$traslado->id}/despachar")
        ->assertConflict()
        ->assertJsonPath('codigo', 'TRANSICION_INVALIDA')
        ->assertJsonPath('detalle.estado_actual', 'BORRADOR');
});

it('solo los regentes aprueban', function () {
    $e = EscenarioTraslado::crear();
    $traslado = $e->borrador([[$e->stockEnOrigen(20), 1]]);
    Sanctum::actingAs($e->auxiliar);
    postJson("/api/traslados/{$traslado->id}/solicitar")->assertOk();

    Sanctum::actingAs(User::factory()->rol(Rol::AuxiliarFarmacia)->create());
    postJson("/api/traslados/{$traslado->id}/aprobar")->assertForbidden();
});

it('anula el creador o un regente, no otro auxiliar', function () {
    $e = EscenarioTraslado::crear();
    $traslado = $e->borrador([[$e->stockEnOrigen(20), 1]]);

    Sanctum::actingAs(User::factory()->rol(Rol::AuxiliarFarmacia)->create());
    postJson("/api/traslados/{$traslado->id}/anular", ['motivo' => 'No corresponde'])->assertForbidden();

    Sanctum::actingAs($e->auxiliar);
    postJson("/api/traslados/{$traslado->id}/anular", ['motivo' => 'No corresponde'])
        ->assertOk()->assertJsonPath('data.estado', 'ANULADO');
});

it('el auditor consulta pero no opera; el médico no accede', function () {
    $e = EscenarioTraslado::crear();
    $traslado = $e->borrador([[$e->stockEnOrigen(20), 1]]);

    Sanctum::actingAs(User::factory()->rol(Rol::Auditor)->create());
    getJson('/api/traslados')->assertOk()->assertJsonPath('data.0.id', $traslado->id);
    postJson("/api/traslados/{$traslado->id}/solicitar")->assertForbidden();

    Sanctum::actingAs(User::factory()->rol(Rol::Medico)->create());
    getJson("/api/traslados/{$traslado->id}")->assertForbidden();
});

it('filtra el listado por estado', function () {
    $e = EscenarioTraslado::crear();
    $e->borrador([[$e->stockEnOrigen(20), 1]]);
    $aprobado = $e->aprobado([[$e->stockEnOrigen(20), 1]]);
    Sanctum::actingAs($e->regente);

    getJson('/api/traslados?estado=APROBADO')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $aprobado->id);
    getJson('/api/traslados?estado=PERDIDO')->assertUnprocessable();
});

it('valida la forma de la petición', function () {
    $e = EscenarioTraslado::crear();
    Sanctum::actingAs($e->auxiliar);

    postJson('/api/traslados', ['bodega_origen_id' => $e->origen->id, 'bodega_destino_id' => $e->origen->id, 'items' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['bodega_destino_id', 'items']);
});
