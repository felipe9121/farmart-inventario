<?php

declare(strict_types=1);

use App\Actions\Dispensaciones\RegistrarDispensacion;
use App\Enums\AccionBitacora;
use App\Enums\Rol;
use App\Models\BitacoraAccesoPaciente;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\Support\EscenarioDispensacion;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

it('registra el acceso al paciente al dispensar por la API', function () {
    $e = EscenarioDispensacion::crear();
    $item = $e->item();
    $e->stock($item, 50);
    Sanctum::actingAs($e->auxiliar);

    postJson('/api/dispensaciones', [
        'paciente_id' => $e->paciente->id, 'prescripcion_id' => $e->prescripcion->id, 'bodega_id' => $e->bodega->id,
        'items' => [['prescripcion_item_id' => $item->id, 'cantidad' => 1]],
    ], ['Idempotency-Key' => 'k-1'])->assertCreated();

    $registro = BitacoraAccesoPaciente::query()->sole();
    expect($registro->accion)->toBe(AccionBitacora::Dispensacion)
        ->and($registro->paciente_id)->toBe($e->paciente->id)
        ->and($registro->usuario_id)->toBe($e->auxiliar->id)
        ->and($registro->ip)->toBe('127.0.0.1');
});

it('no registra acceso si la dispensación falla ni al repetirla', function () {
    $e = EscenarioDispensacion::crear();
    $item = $e->item();
    $accion = app(RegistrarDispensacion::class);

    capturarError(fn () => $accion->ejecutar($e->auxiliar, $e->datos([[$item, 1]]), 'k-1'));
    expect(BitacoraAccesoPaciente::query()->count())->toBe(0);

    $e->stock($item, 50);
    $accion->ejecutar($e->auxiliar, $e->datos([[$item, 1]]), 'k-2');
    $accion->ejecutar($e->auxiliar, $e->datos([[$item, 1]]), 'k-2');
    expect(BitacoraAccesoPaciente::query()->count())->toBe(1);
});

it('el auditor consulta la bitácora con filtros', function () {
    $buscado = BitacoraAccesoPaciente::factory()->create();
    BitacoraAccesoPaciente::factory()->create();
    Sanctum::actingAs(User::factory()->rol(Rol::Auditor)->create());

    getJson("/api/bitacora-accesos?paciente_id={$buscado->paciente_id}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $buscado->id)
        ->assertJsonPath('data.0.accion', 'CONSULTA_DETALLE');
});

it('solo auditor y admin consultan la bitácora', function (Rol $rol, int $estado) {
    Sanctum::actingAs(User::factory()->rol($rol)->create());

    getJson('/api/bitacora-accesos')->assertStatus($estado);
})->with([
    'auditor' => [Rol::Auditor, 200],
    'admin' => [Rol::Admin, 200],
    'regente' => [Rol::RegenteFarmacia, 403],
    'médico' => [Rol::Medico, 403],
]);
