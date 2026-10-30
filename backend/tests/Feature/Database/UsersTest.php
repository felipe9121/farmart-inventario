<?php

declare(strict_types=1);

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('guarda el rol como enum', function () {
    $user = User::factory()->rol(Rol::RegenteFarmacia)->create();

    expect($user->fresh()->rol)->toBe(Rol::RegenteFarmacia)
        ->and($user->tieneRol(Rol::RegenteFarmacia, Rol::Admin))->toBeTrue();
});

it('rechaza en la BD un rol inexistente', function () {
    DB::table('users')->insert([
        'name' => 'X', 'email' => 'x@example.com', 'password' => 'x', 'rol' => 'superusuario',
    ]);
})->throws(QueryException::class, 'ck_users_rol');
