<?php

declare(strict_types=1);

use App\Enums\Rol;
use App\Enums\TipoMovimiento;
use App\Models\Bodega;
use App\Models\Existencia;
use App\Models\Lote;
use App\Models\MovimientoKardex;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

function regenteAutenticado(): User
{
    $regente = User::factory()->rol(Rol::RegenteFarmacia)->create();
    Sanctum::actingAs($regente);

    return $regente;
}

it('registra un ajuste negativo con motivo y responde el movimiento', function () {
    $regente = regenteAutenticado();
    $existencia = Existencia::factory()->create(['cantidad' => 10]);

    postJson('/api/ajustes', [
        'bodega_id' => $existencia->bodega_id, 'lote_id' => $existencia->lote_id, 'cantidad' => -3, 'motivo' => 'Unidades averiadas',
    ])
        ->assertCreated()
        ->assertJsonPath('data.tipo', 'AJUSTE_NEGATIVO')
        ->assertJsonPath('data.cantidad', -3)
        ->assertJsonPath('data.saldo_resultante', 7)
        ->assertJsonPath('data.motivo', 'Unidades averiadas')
        ->assertJsonPath('data.usuario_id', $regente->id);

    expect($existencia->refresh()->cantidad)->toBe(7);
});

it('un ajuste positivo crea la existencia si no había', function () {
    regenteAutenticado();
    $bodega = Bodega::factory()->create();
    $lote = Lote::factory()->create();

    postJson('/api/ajustes', ['bodega_id' => $bodega->id, 'lote_id' => $lote->id, 'cantidad' => 4, 'motivo' => 'Conteo físico'])
        ->assertCreated()
        ->assertJsonPath('data.tipo', TipoMovimiento::AjustePositivo->value);

    expect(Existencia::query()->where('bodega_id', $bodega->id)->where('lote_id', $lote->id)->value('cantidad'))->toBe(4);
});

it('permite dar de baja un lote vencido', function () {
    regenteAutenticado();
    $lote = Lote::factory()->vencido()->create();
    $existencia = Existencia::factory()->create(['lote_id' => $lote->id, 'cantidad' => 5]);

    postJson('/api/ajustes', ['bodega_id' => $existencia->bodega_id, 'lote_id' => $lote->id, 'cantidad' => -5, 'motivo' => 'Baja por vencimiento'])
        ->assertCreated();

    expect($existencia->refresh()->cantidad)->toBe(0);
});

it('no deja el stock negativo con un ajuste', function () {
    regenteAutenticado();
    $existencia = Existencia::factory()->create(['cantidad' => 2]);

    postJson('/api/ajustes', ['bodega_id' => $existencia->bodega_id, 'lote_id' => $existencia->lote_id, 'cantidad' => -3, 'motivo' => 'Faltante'])
        ->assertUnprocessable()
        ->assertJsonPath('codigo', 'STOCK_INSUFICIENTE');

    expect(MovimientoKardex::query()->count())->toBe(0);
});

it('exige motivo y cantidad distinta de cero', function () {
    regenteAutenticado();
    $existencia = Existencia::factory()->create();

    postJson('/api/ajustes', ['bodega_id' => $existencia->bodega_id, 'lote_id' => $existencia->lote_id, 'cantidad' => 0, 'motivo' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['cantidad', 'motivo']);
});

it('solo los regentes registran ajustes', function (Rol $rol) {
    Sanctum::actingAs(User::factory()->rol($rol)->create());
    $existencia = Existencia::factory()->create();

    postJson('/api/ajustes', ['bodega_id' => $existencia->bodega_id, 'lote_id' => $existencia->lote_id, 'cantidad' => -1, 'motivo' => 'Prueba de rol'])
        ->assertForbidden();
})->with([Rol::AuxiliarFarmacia, Rol::Auditor, Rol::Medico, Rol::Admin]);
