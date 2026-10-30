<?php

declare(strict_types=1);

use App\Enums\Rol;
use App\Enums\TipoMovimiento;
use App\Models\MovimientoKardex;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

it('lista el kardex del más reciente al más antiguo', function () {
    Sanctum::actingAs(User::factory()->rol(Rol::Auditor)->create());
    $primero = MovimientoKardex::factory()->create();
    $segundo = MovimientoKardex::factory()->create();

    getJson('/api/kardex')
        ->assertOk()
        ->assertJsonPath('data.0.id', $segundo->id)
        ->assertJsonPath('data.1.id', $primero->id)
        ->assertJsonPath('data.0.tipo', 'ENTRADA');
});

it('filtra por bodega, lote y tipo', function () {
    Sanctum::actingAs(User::factory()->rol(Rol::RegenteFarmacia)->create());
    $buscado = MovimientoKardex::factory()->create();
    MovimientoKardex::factory()->create();
    MovimientoKardex::factory()->create([
        'bodega_id' => $buscado->bodega_id, 'lote_id' => $buscado->lote_id, 'producto_id' => $buscado->producto_id,
        'tipo' => TipoMovimiento::AjusteNegativo, 'cantidad' => -1, 'saldo_resultante' => 0, 'motivo' => 'x',
    ]);

    getJson("/api/kardex?bodega_id={$buscado->bodega_id}&lote_id={$buscado->lote_id}&tipo=ENTRADA")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $buscado->id);
});

it('valida los filtros', function () {
    Sanctum::actingAs(User::factory()->rol(Rol::Auditor)->create());

    getJson('/api/kardex?tipo=ROBO&desde=2026-10-10&hasta=2026-10-01')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tipo', 'hasta']);
});

it('el médico no consulta el kardex', function () {
    Sanctum::actingAs(User::factory()->rol(Rol::Medico)->create());

    getJson('/api/kardex')->assertForbidden();
});
