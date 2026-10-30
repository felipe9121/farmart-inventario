<?php

declare(strict_types=1);

use App\Enums\AccionBitacora;
use App\Models\BitacoraAccesoPaciente;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\ComoAdministradorBd;

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

it('el usuario de la app no tiene permiso de editar ni borrar la bitácora', function (string $operacion) {
    $registro = BitacoraAccesoPaciente::factory()->create();
    $consulta = DB::table('bitacora_accesos_paciente')->where('id', $registro->id);

    $operacion === 'update' ? $consulta->update(['accion' => 'BUSQUEDA']) : $consulta->delete();
})->with(['update', 'delete'])->throws(QueryException::class, 'permission was denied');

it('aun con permisos de administrador, el trigger impide editar o borrar la bitácora', function (string $operacion) {
    ComoAdministradorBd::enTransaccion(function (Connection $db) use ($operacion) {
        $consulta = $db->table('bitacora_accesos_paciente')->where('id', ComoAdministradorBd::insertarRegistroBitacora($db));

        $operacion === 'update' ? $consulta->update(['accion' => 'CONSULTA_DETALLE']) : $consulta->delete();
    });
})->with(['update', 'delete'])->throws(QueryException::class, 'bitacora_accesos_paciente es inmutable');

it('falla en el modelo al intentar eliminar', function () {
    BitacoraAccesoPaciente::factory()->create()->delete();
})->throws(LogicException::class, 'inmutable');
