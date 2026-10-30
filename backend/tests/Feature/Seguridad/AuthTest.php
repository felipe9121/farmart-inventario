<?php

declare(strict_types=1);

use App\Enums\Rol;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\travel;

it('emite un token con expiración y devuelve el usuario', function () {
    $usuario = User::factory()->rol(Rol::RegenteFarmacia)->create(['email' => 'regente@farmart.test']);

    $respuesta = postJson('/api/auth/login', ['email' => 'regente@farmart.test', 'password' => 'password']);

    $respuesta->assertOk()
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('usuario.id', $usuario->id)
        ->assertJsonPath('usuario.rol', 'regente_farmacia')
        ->assertJsonMissingPath('usuario.password');
    expect($respuesta->json('expires_at'))->not->toBeNull()
        ->and(PersonalAccessToken::query()->sole()->expires_at)->not->toBeNull();
});

it('el token emitido da acceso a la API', function () {
    User::factory()->create(['email' => 'aux@farmart.test']);
    $token = postJson('/api/auth/login', ['email' => 'aux@farmart.test', 'password' => 'password'])->json('token');

    getJson('/api/auth/me', ['Authorization' => "Bearer {$token}"])->assertOk()->assertJsonPath('data.email', 'aux@farmart.test');
});

it('responde igual ante clave errónea, usuario inexistente o inactivo', function (string $email, string $password) {
    User::factory()->create(['email' => 'activo@farmart.test']);
    User::factory()->create(['email' => 'inactivo@farmart.test', 'activo' => false]);

    postJson('/api/auth/login', ['email' => $email, 'password' => $password])
        ->assertUnauthorized()
        ->assertExactJson(['message' => 'Credenciales inválidas.', 'codigo' => 'CREDENCIALES_INVALIDAS']);
})->with([
    'clave errónea' => ['activo@farmart.test', 'otra'],
    'inexistente' => ['nadie@farmart.test', 'password'],
    'inactivo' => ['inactivo@farmart.test', 'password'],
]);

it('limita los intentos de login', function () {
    User::factory()->create(['email' => 'aux@farmart.test']);

    foreach (range(1, 5) as $intento) {
        postJson('/api/auth/login', ['email' => 'aux@farmart.test', 'password' => 'mala'])->assertUnauthorized();
    }

    postJson('/api/auth/login', ['email' => 'aux@farmart.test', 'password' => 'password'])->assertTooManyRequests();
});

it('revoca el token al cerrar sesión', function () {
    User::factory()->create(['email' => 'aux@farmart.test']);
    $token = postJson('/api/auth/login', ['email' => 'aux@farmart.test', 'password' => 'password'])->json('token');

    postJson('/api/auth/logout', [], ['Authorization' => "Bearer {$token}"])->assertNoContent();

    expect(PersonalAccessToken::query()->count())->toBe(0);
});

it('un usuario desactivado pierde el acceso aunque tenga token', function () {
    $usuario = User::factory()->create(['email' => 'aux@farmart.test']);
    $token = postJson('/api/auth/login', ['email' => 'aux@farmart.test', 'password' => 'password'])->json('token');
    $usuario->forceFill(['activo' => false])->save();

    getJson('/api/auth/me', ['Authorization' => "Bearer {$token}"])
        ->assertForbidden()
        ->assertJsonPath('codigo', 'USUARIO_INACTIVO');
});

it('un token expirado no sirve', function () {
    User::factory()->create(['email' => 'aux@farmart.test']);
    $token = postJson('/api/auth/login', ['email' => 'aux@farmart.test', 'password' => 'password'])->json('token');

    travel(481)->minutes();

    getJson('/api/auth/me', ['Authorization' => "Bearer {$token}"])->assertUnauthorized();
});

it('ya no expone /api/user', function () {
    getJson('/api/user')->assertNotFound();
});
