<?php

declare(strict_types=1);

use App\Enums\AccionBitacora;
use App\Enums\Rol;
use App\Models\BitacoraAccesoPaciente;
use App\Models\Paciente;
use App\Models\Prescripcion;
use App\Models\PrescripcionItem;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

function pacienteDemo(): Paciente
{
    return Paciente::factory()->create([
        'numero_documento' => '9012345678', 'nombres' => 'María José', 'apellidos' => 'Pérez Gómez',
        'fecha_nacimiento' => '1985-03-14', 'telefono' => '3001234567',
    ]);
}

function como(Rol $rol): User
{
    $usuario = User::factory()->rol($rol)->create();
    Sanctum::actingAs($usuario);

    return $usuario;
}

it('busca por documento exacto y registra la búsqueda sin el término', function () {
    $paciente = pacienteDemo();
    $usuario = como(Rol::AuxiliarFarmacia);

    getJson('/api/pacientes?tipo_documento=CC&numero_documento=9012345678')
        ->assertOk()
        ->assertJsonPath('data.0.id', $paciente->id)
        ->assertJsonPath('data.0.nombres', 'María José')
        ->assertJsonPath('data.0.enmascarado', false);

    $registro = BitacoraAccesoPaciente::query()->sole();
    expect($registro->accion)->toBe(AccionBitacora::Busqueda)
        ->and($registro->paciente_id)->toBe($paciente->id)
        ->and($registro->usuario_id)->toBe($usuario->id)
        ->and($registro->rol)->toBe(Rol::AuxiliarFarmacia)
        ->and($registro->ip)->toBe('127.0.0.1');
});

it('registra también las búsquedas sin resultado', function () {
    como(Rol::RegenteFarmacia);

    getJson('/api/pacientes?tipo_documento=CC&numero_documento=0000')->assertOk()->assertJsonCount(0, 'data');

    expect(BitacoraAccesoPaciente::query()->sole()->paciente_id)->toBeNull();
});

it('exige tipo y número de documento: no hay listado general', function () {
    pacienteDemo();
    como(Rol::RegenteFarmacia);

    getJson('/api/pacientes')->assertUnprocessable()->assertJsonValidationErrors(['tipo_documento', 'numero_documento']);
});

it('enmascara los datos para el auditor (RN-10)', function () {
    $paciente = pacienteDemo();
    como(Rol::Auditor);

    $respuesta = getJson("/api/pacientes/{$paciente->id}")->assertOk();

    $respuesta->assertJsonPath('data.nombres', 'M*** J***')
        ->assertJsonPath('data.apellidos', 'P*** G***')
        ->assertJsonPath('data.numero_documento', '******5678')
        ->assertJsonPath('data.fecha_nacimiento', '1985-**-**')
        ->assertJsonPath('data.telefono', '********67')
        ->assertJsonPath('data.enmascarado', true);
    $contenido = json_encode($respuesta->json(), JSON_UNESCAPED_UNICODE);
    expect($contenido)->not()->toContain('María')
        ->and($contenido)->not()->toContain('9012345678');
});

it('muestra los datos completos a médico, auxiliar y regente', function (Rol $rol) {
    $paciente = pacienteDemo();
    como($rol);

    getJson("/api/pacientes/{$paciente->id}")->assertOk()->assertJsonPath('data.numero_documento', '9012345678');

    expect(BitacoraAccesoPaciente::query()->sole()->accion)->toBe(AccionBitacora::ConsultaDetalle);
})->with([Rol::Medico, Rol::AuxiliarFarmacia, Rol::RegenteFarmacia]);

it('el admin no accede a datos de pacientes y no deja rastro de acceso', function () {
    $paciente = pacienteDemo();
    como(Rol::Admin);

    getJson("/api/pacientes/{$paciente->id}")->assertForbidden();
    getJson('/api/pacientes?tipo_documento=CC&numero_documento=9012345678')->assertForbidden();

    expect(BitacoraAccesoPaciente::query()->count())->toBe(0);
});

it('lista las prescripciones con lo pendiente y registra la consulta', function () {
    $paciente = pacienteDemo();
    $vigente = Prescripcion::factory()->create(['paciente_id' => $paciente->id]);
    PrescripcionItem::factory()->create(['prescripcion_id' => $vigente->id, 'cantidad_prescrita' => 10]);
    Prescripcion::factory()->vencida()->create(['paciente_id' => $paciente->id]);
    como(Rol::AuxiliarFarmacia);

    getJson("/api/pacientes/{$paciente->id}/prescripciones")->assertOk()->assertJsonCount(2, 'data');
    getJson("/api/pacientes/{$paciente->id}/prescripciones?vigentes=1")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $vigente->id)
        ->assertJsonPath('data.0.vigente', true)
        ->assertJsonPath('data.0.items.0.cantidad_pendiente', 10);

    expect(BitacoraAccesoPaciente::query()->where('accion', AccionBitacora::ConsultaPrescripciones)->count())->toBe(2);
});

it('las respuestas de la API no se guardan en caché', function () {
    como(Rol::RegenteFarmacia);

    getJson('/api/pacientes?tipo_documento=CC&numero_documento=1')
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});
