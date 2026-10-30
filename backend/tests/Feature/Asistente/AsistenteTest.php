<?php

declare(strict_types=1);

use App\Enums\Rol;
use App\Models\Bodega;
use App\Models\Existencia;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\StockMinimo;
use App\Models\User;
use App\Services\Asistente\Intencion;
use App\Services\Asistente\Interpretacion;
use App\Services\Asistente\ModeloLenguaje;
use Illuminate\Http\JsonResponse;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

/**
 * @return TestResponse<JsonResponse>
 */
function preguntar(string $pregunta): TestResponse
{
    return postJson('/api/asistente/preguntas', ['pregunta' => $pregunta]);
}

function loteEn(Bodega $bodega, string $producto, string $lote, int $diasParaVencer, int $cantidad): void
{
    $p = Producto::factory()->create(['nombre' => $producto]);
    $l = Lote::factory()->venceEnDias($diasParaVencer)->create(['producto_id' => $p->id, 'numero_lote' => $lote]);
    Existencia::factory()->create(['bodega_id' => $bodega->id, 'lote_id' => $l->id, 'producto_id' => $p->id, 'cantidad' => $cantidad]);
}

beforeEach(function () {
    Sanctum::actingAs(User::factory()->rol(Rol::RegenteFarmacia)->create());
});

it('responde qué lotes vencen en una bodega con datos reales', function () {
    $central = Bodega::factory()->create(['codigo' => 'BOD-CEN', 'nombre' => 'Bodega Central']);
    loteEn($central, 'Amoxicilina 500 mg', 'L-AMX', 12, 40);
    loteEn($central, 'Ibuprofeno 400 mg', 'L-IBU', 60, 10);
    loteEn(Bodega::factory()->create(), 'Otro', 'L-OTRO', 5, 10);

    preguntar('¿Qué lotes vencen en los próximos 90 días en la bodega central?')
        ->assertOk()
        ->assertJsonPath('data.intencion', 'LOTES_POR_VENCER')
        ->assertJsonPath('data.filtros.bodega_id', $central->id)
        ->assertJsonPath('data.filtros.dias', 90)
        ->assertJsonCount(2, 'data.datos')
        ->assertJsonPath('data.modelo', 'simulado')
        ->assertJsonPath('data.respuesta', fn (string $texto) => str_contains($texto, 'la Bodega Central hay 2 lotes')
            && str_contains($texto, 'Amoxicilina 500 mg, lote L-AMX')
            && str_contains($texto, '(40 unidades)'));
});

it('menciona los lotes ya vencidos que aún tienen stock', function () {
    loteEn(Bodega::factory()->create(), 'Losartán 50 mg', 'L-LOS', -2, 7);

    preguntar('¿qué medicamentos están vencidos?')
        ->assertOk()
        ->assertJsonPath('data.respuesta', fn (string $texto) => str_contains($texto, '(1 ya vencido)')
            && str_contains($texto, 'aún tiene 7 unidades'));
});

it('responde qué productos están bajo el mínimo', function () {
    $norte = Bodega::factory()->create(['codigo' => 'FAR-NOR', 'nombre' => 'Farmacia Sede Norte']);
    $producto = Producto::factory()->create(['nombre' => 'Metformina 850 mg']);
    StockMinimo::factory()->create(['bodega_id' => $norte->id, 'producto_id' => $producto->id, 'cantidad_minima' => 50]);

    preguntar('¿Qué debo reponer en la sede norte?')
        ->assertOk()
        ->assertJsonPath('data.intencion', 'STOCK_BAJO_MINIMO')
        ->assertJsonPath('data.datos.0.faltante', 50)
        ->assertJsonPath('data.respuesta', 'En la Farmacia Sede Norte hay 1 producto bajo el stock mínimo. El de mayor faltante es Metformina 850 mg en Farmacia Sede Norte: 0 de 50 unidades (faltan 50).');
});

it('lo dice cuando no hay alertas', function () {
    preguntar('¿Qué está bajo el mínimo?')
        ->assertOk()
        ->assertJsonPath('data.respuesta', 'Ningún producto está bajo el stock mínimo en todas las bodegas.');
});

it('ante otra pregunta explica su alcance y sugiere las dos que sabe responder', function () {
    preguntar('¿Cuál es el teléfono del paciente?')
        ->assertOk()
        ->assertJsonPath('data.intencion', 'DESCONOCIDA')
        ->assertJsonCount(0, 'data.datos')
        ->assertJsonCount(2, 'data.sugerencias');
});

it('no registra la pregunta en los logs', function () {
    $registrado = [];
    Event::listen(MessageLogged::class, function (MessageLogged $e) use (&$registrado) {
        $registrado[] = $e->message.json_encode($e->context);
    });

    preguntar('¿Qué vence? El paciente Rosalba Pérez CC 9012345678 lo necesita')->assertOk();

    expect(implode(' ', $registrado))->not()->toContain('9012345678');
});

it('usa el modelo configurado a través del contrato', function () {
    $falso = new class implements ModeloLenguaje
    {
        public function interpretar(string $pregunta, array $bodegas): Interpretacion
        {
            return new Interpretacion(Intencion::StockBajoMinimo);
        }

        public function nombre(): string
        {
            return 'falso';
        }
    };
    app()->instance(ModeloLenguaje::class, $falso);

    preguntar('cualquier cosa')->assertJsonPath('data.intencion', 'STOCK_BAJO_MINIMO')->assertJsonPath('data.modelo', 'falso');
});

it('valida la pregunta y restringe el acceso', function () {
    preguntar('')->assertUnprocessable();

    Sanctum::actingAs(User::factory()->rol(Rol::Medico)->create());
    preguntar('¿Qué vence?')->assertForbidden();
});
