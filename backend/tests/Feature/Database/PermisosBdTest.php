<?php

declare(strict_types=1);

use App\Models\Existencia;
use App\Models\Paciente;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
| Mínimo privilegio: la app (y por tanto estas pruebas) se conecta con un usuario que solo
| lee y escribe datos.
*/

it('la app no se conecta como administrador', function () {
    expect(DB::scalar('SELECT USER_NAME()'))->toBe('farmart_app')
        ->and(DB::scalar("SELECT IS_ROLEMEMBER('db_owner')"))->toBe(0)
        ->and(DB::scalar("SELECT IS_SRVROLEMEMBER('sysadmin')"))->toBe(0);
});

it('no puede crear ni borrar tablas', function (string $sql) {
    DB::statement($sql);
})->with([
    'crear' => ['CREATE TABLE intrusa (id int)'],
    'borrar' => ['DROP TABLE movimientos_kardex'],
])->throws(QueryException::class);

it('no puede desactivar los triggers del kardex', function () {
    DB::statement('DISABLE TRIGGER tr_movimientos_kardex_no_update ON movimientos_kardex');
})->throws(QueryException::class);

it('no puede borrar documentos con historia', function () {
    $existencia = Existencia::factory()->create();

    DB::table('existencias')->where('id', $existencia->id)->delete();
})->throws(QueryException::class, 'permission was denied');

it('sí puede leer, crear y actualizar datos', function () {
    $paciente = Paciente::factory()->create();
    $paciente->update(['telefono' => '3000000000']);

    expect($paciente->refresh()->telefono)->toBe('3000000000');
});
