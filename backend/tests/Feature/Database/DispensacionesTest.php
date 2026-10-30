<?php

declare(strict_types=1);

use App\Enums\Rol;
use App\Models\Dispensacion;
use App\Models\DispensacionItem;
use App\Models\User;
use Illuminate\Database\QueryException;

it('no permite reutilizar la llave de idempotencia del mismo usuario (RN-09)', function () {
    $usuario = User::factory()->create();

    Dispensacion::factory()->create(['dispensado_por' => $usuario->id, 'idempotency_key' => 'llave-1']);
    Dispensacion::factory()->create(['dispensado_por' => $usuario->id, 'idempotency_key' => 'llave-1']);
})->throws(QueryException::class, 'uq_dispensaciones_idempotencia');

it('permite la misma llave para usuarios distintos', function () {
    Dispensacion::factory()->count(2)->create(['idempotency_key' => 'llave-1']);

    expect(Dispensacion::query()->where('idempotency_key', 'llave-1')->count())->toBe(2);
});

it('admite varias dispensaciones sin llave gracias al UNIQUE filtrado', function () {
    $usuario = User::factory()->create();

    Dispensacion::factory()->count(2)->create([
        'dispensado_por' => $usuario->id, 'idempotency_key' => null, 'idempotency_hash' => null,
    ]);

    expect(Dispensacion::query()->whereNull('idempotency_key')->count())->toBe(2);
});

it('exige el hash del payload cuando hay llave de idempotencia', function () {
    Dispensacion::factory()->create(['idempotency_hash' => null]);
})->throws(QueryException::class, 'ck_dispensaciones_idempotencia');

it('rechaza un hash de payload sin llave de idempotencia', function () {
    Dispensacion::factory()->create(['idempotency_key' => null]);
})->throws(QueryException::class, 'ck_dispensaciones_idempotencia');

it('impide que quien dispensa se autorice a sí mismo (RN-05)', function () {
    $regente = User::factory()->rol(Rol::RegenteFarmacia)->create();

    Dispensacion::factory()->autorizadaPor($regente)->create(['dispensado_por' => $regente->id]);
})->throws(QueryException::class, 'ck_dispensaciones_autorizador_distinto');

it('acepta la autorización de un segundo usuario', function () {
    $regente = User::factory()->rol(Rol::RegenteFarmacia)->create();

    $dispensacion = Dispensacion::factory()->autorizadaPor($regente)->create();

    expect($dispensacion->autorizador->is($regente))->toBeTrue();
});

it('exige la fecha de autorización junto con el autorizador', function () {
    $regente = User::factory()->rol(Rol::RegenteFarmacia)->create();

    Dispensacion::factory()->create(['autorizado_por' => $regente->id, 'autorizado_at' => null]);
})->throws(QueryException::class, 'ck_dispensaciones_autorizacion_completa');

it('rechaza ítems con cantidad no positiva', function () {
    DispensacionItem::factory()->create(['cantidad' => 0]);
})->throws(QueryException::class, 'ck_dispensacion_items_cantidad');
