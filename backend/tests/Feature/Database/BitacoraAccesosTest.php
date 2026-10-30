<?php

declare(strict_types=1);

use App\Enums\AccionBitacora;
use App\Models\BitacoraAccesoPaciente;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('registra búsquedas sin paciente', function () {
    $registro = BitacoraAccesoPaciente::factory()->create(['paciente_id' => null, 'accion' => AccionBitacora::Busqueda]);

    expect($registro->fresh()->created_at)->not->toBeNull();
});

it('rechaza en la BD acciones no definidas', function () {
    $registro = BitacoraAccesoPaciente::factory()->make();

    DB::table('bitacora_accesos_paciente')->insert([
        'usuario_id' => $registro->usuario_id, 'rol' => 'auditor', 'accion' => 'EXPORTAR',
    ]);
})->throws(QueryException::class, 'ck_bitacora_accesos_accion');

it('impide actualizar la bitácora en la BD', function () {
    $registro = BitacoraAccesoPaciente::factory()->create();

    DB::table('bitacora_accesos_paciente')->where('id', $registro->id)->update(['accion' => 'BUSQUEDA']);
})->throws(QueryException::class, 'bitacora_accesos_paciente es inmutable');

it('impide eliminar la bitácora en la BD', function () {
    $registro = BitacoraAccesoPaciente::factory()->create();

    DB::table('bitacora_accesos_paciente')->where('id', $registro->id)->delete();
})->throws(QueryException::class, 'bitacora_accesos_paciente es inmutable');

it('falla en el modelo al intentar eliminar', function () {
    BitacoraAccesoPaciente::factory()->create()->delete();
})->throws(LogicException::class, 'inmutable');
