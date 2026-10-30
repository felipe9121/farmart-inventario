<?php

declare(strict_types=1);

use App\Enums\Rol;
use App\Models\Bodega;
use App\Models\Existencia;
use App\Models\Lote;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

it('lista las bodegas activas a cualquier usuario autenticado', function () {
    Bodega::factory()->create(['nombre' => 'B activa']);
    Bodega::factory()->create(['activa' => false]);
    Sanctum::actingAs(User::factory()->rol(Rol::Medico)->create());

    getJson('/api/bodegas')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.nombre', 'B activa');
});

it('lista las existencias con stock de una bodega, marcando los lotes vencidos', function () {
    $bodega = Bodega::factory()->create();
    $vencido = Lote::factory()->vencido()->create();
    Existencia::factory()->create(['bodega_id' => $bodega->id, 'lote_id' => $vencido->id, 'cantidad' => 4]);
    Existencia::factory()->create(['bodega_id' => $bodega->id, 'cantidad' => 0]);
    Sanctum::actingAs(User::factory()->rol(Rol::AuxiliarFarmacia)->create());

    getJson("/api/bodegas/{$bodega->id}/existencias")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.cantidad', 4)
        ->assertJsonPath('data.0.lote.vencido', true);
});

it('el médico no ve existencias', function () {
    Sanctum::actingAs(User::factory()->rol(Rol::Medico)->create());

    getJson('/api/bodegas/'.Bodega::factory()->create()->id.'/existencias')->assertForbidden();
});
