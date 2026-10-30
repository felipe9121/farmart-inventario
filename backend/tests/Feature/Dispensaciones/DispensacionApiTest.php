<?php

declare(strict_types=1);

use App\Enums\Rol;
use App\Models\Dispensacion;
use App\Models\PrescripcionItem;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Support\EscenarioDispensacion;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

/**
 * @param  list<array{PrescripcionItem, int}>  $items
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function cuerpo(EscenarioDispensacion $e, array $items, array $extra = []): array
{
    return [
        'paciente_id' => $e->paciente->id,
        'prescripcion_id' => $e->prescripcion->id,
        'bodega_id' => $e->bodega->id,
        'items' => array_map(fn (array $par) => ['prescripcion_item_id' => $par[0]->id, 'cantidad' => $par[1]], $items),
        ...$extra,
    ];
}

/**
 * @param  array<string, mixed>  $cuerpo
 * @return TestResponse<JsonResponse>
 */
function postDispensacion(array $cuerpo, ?string $llave = 'llave-1'): TestResponse
{
    return postJson('/api/dispensaciones', $cuerpo, $llave !== null ? ['Idempotency-Key' => $llave] : []);
}

it('crea la dispensación y responde 201 sin datos del paciente', function () {
    $e = EscenarioDispensacion::crear();
    $item = $e->item();
    $existencia = $e->stock($item, 50);
    Sanctum::actingAs($e->auxiliar);

    $respuesta = postDispensacion(cuerpo($e, [[$item, 3]]));

    $respuesta->assertCreated()
        ->assertHeader('Idempotent-Replayed', 'false')
        ->assertJsonPath('data.dispensado_por', $e->auxiliar->id)
        ->assertJsonPath('data.items.0.cantidad', 3)
        ->assertJsonPath('data.items.0.lote.id', $existencia->lote_id)
        ->assertJsonMissingPath('data.paciente');
    // Sin escapar unicode: si no, un nombre con tilde nunca coincidiría aunque estuviera.
    $contenido = json_encode($respuesta->json(), JSON_UNESCAPED_UNICODE);
    expect($contenido)->not()->toContain($e->paciente->nombres)
        ->and($contenido)->not()->toContain($e->paciente->apellidos)
        ->and($contenido)->not()->toContain($e->paciente->numero_documento);
});

it('repite la respuesta con 200 al reenviar la misma llave', function () {
    $e = EscenarioDispensacion::crear();
    $item = $e->item();
    $e->stock($item, 50);
    Sanctum::actingAs($e->auxiliar);

    $primera = postDispensacion(cuerpo($e, [[$item, 3]]))->assertCreated();
    $segunda = postDispensacion(cuerpo($e, [[$item, 3]]));

    $segunda->assertOk()
        ->assertHeader('Idempotent-Replayed', 'true')
        ->assertJsonPath('data.id', $primera->json('data.id'));
    expect(Dispensacion::query()->count())->toBe(1);
});

it('responde 422 si la llave se reutiliza con otro contenido', function () {
    $e = EscenarioDispensacion::crear();
    $item = $e->item();
    $e->stock($item, 50);
    Sanctum::actingAs($e->auxiliar);

    postDispensacion(cuerpo($e, [[$item, 3]]))->assertCreated();

    postDispensacion(cuerpo($e, [[$item, 4]]))
        ->assertUnprocessable()
        ->assertJsonPath('codigo', 'IDEMPOTENCY_KEY_REUTILIZADA');
});

it('responde 400 sin header Idempotency-Key o con uno inválido', function (?string $llave) {
    $e = EscenarioDispensacion::crear();
    Sanctum::actingAs($e->auxiliar);

    postDispensacion(cuerpo($e, [[$e->item(), 1]]), $llave)
        ->assertBadRequest()
        ->assertJsonPath('codigo', 'IDEMPOTENCY_KEY_REQUERIDA');
})->with([
    'sin header' => [null],
    'vacío' => [''],
    'demasiado largo' => [str_repeat('a', 101)],
    'con espacios' => ['llave con espacios'],
]);

it('responde 401 sin autenticación', function () {
    postDispensacion([])->assertUnauthorized();
});

it('solo auxiliares y regentes pueden dispensar', function (Rol $rol) {
    $e = EscenarioDispensacion::crear();
    Sanctum::actingAs(User::factory()->rol($rol)->create());

    postDispensacion(cuerpo($e, [[$e->item(), 1]]))->assertForbidden();
})->with([Rol::Medico, Rol::Auditor, Rol::Admin]);

it('un usuario inactivo no puede dispensar', function () {
    $e = EscenarioDispensacion::crear();
    Sanctum::actingAs(User::factory()->rol(Rol::AuxiliarFarmacia)->create(['activo' => false]));

    postDispensacion(cuerpo($e, [[$e->item(), 1]]))->assertForbidden();
});

it('valida la forma de la petición', function () {
    $e = EscenarioDispensacion::crear();
    $item = $e->item();
    Sanctum::actingAs($e->auxiliar);

    postDispensacion(cuerpo($e, [[$item, 0], [$item, 1]]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['items.0.cantidad', 'items.0.prescripcion_item_id']);
});

it('devuelve las reglas de negocio como 422 con código y detalle', function () {
    $e = EscenarioDispensacion::crear();
    $item = $e->item();
    $e->stock($item, 2);
    Sanctum::actingAs($e->auxiliar);

    postDispensacion(cuerpo($e, [[$item, 5]]))
        ->assertUnprocessable()
        ->assertJsonPath('codigo', 'STOCK_INSUFICIENTE')
        ->assertJsonPath('detalle.disponible', 2);
});

it('dispensa control especial con la co-firma del regente (RN-05)', function () {
    $e = EscenarioDispensacion::crear();
    $item = $e->item(controlEspecial: true);
    $e->stock($item, 50);
    Sanctum::actingAs($e->auxiliar);

    postDispensacion(cuerpo($e, [[$item, 1]], ['autorizacion' => ['email' => $e->regente->email, 'password' => 'password']]))
        ->assertCreated()
        ->assertJsonPath('data.autorizado_por', $e->regente->id);
});

it('no devuelve la clave del autorizador en los errores', function () {
    $e = EscenarioDispensacion::crear();
    $item = $e->item(controlEspecial: true);
    $e->stock($item, 50);
    Sanctum::actingAs($e->auxiliar);

    $respuesta = postDispensacion(cuerpo($e, [[$item, 1]], ['autorizacion' => ['email' => $e->regente->email, 'password' => 'ClaveSecreta-123']]));

    $respuesta->assertUnprocessable()->assertJsonPath('codigo', 'AUTORIZACION_INVALIDA');
    expect($respuesta->getContent())->not()->toContain('ClaveSecreta-123');
});

it('permite consultar una dispensación a los roles autorizados', function (Rol $rol, int $estado) {
    $e = EscenarioDispensacion::crear();
    $item = $e->item();
    $e->stock($item, 50);
    Sanctum::actingAs($e->auxiliar);
    $id = postDispensacion(cuerpo($e, [[$item, 1]]))->json('data.id');

    Sanctum::actingAs(User::factory()->rol($rol)->create());

    getJson("/api/dispensaciones/{$id}")->assertStatus($estado);
})->with([
    'auditor' => [Rol::Auditor, 200],
    'regente' => [Rol::RegenteFarmacia, 200],
    'médico' => [Rol::Medico, 403],
]);
