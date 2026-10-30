<?php

declare(strict_types=1);

use App\Actions\Dispensaciones\DatosDispensacion;
use App\Actions\Dispensaciones\RegistrarDispensacion;
use App\Enums\Rol;
use App\Exceptions\ReglaNegocioException;
use App\Models\Bodega;
use App\Models\Dispensacion;
use App\Models\Existencia;
use App\Models\Lote;
use App\Models\MovimientoKardex;
use App\Models\Prescripcion;
use App\Models\PrescripcionItem;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;

/*
| Concurrencia real: cada tarea corre en un proceso PHP distinto con su propia conexión
| a SQL Server. Los datos se confirman (sin la transacción de RefreshDatabase) y la base
| se recrea antes de cada prueba.
*/

beforeEach(function () {
    Artisan::call('migrate:fresh');
});

/*
| Las tareas se crean en funciones globales: un closure definido dentro de la prueba
| queda ligado a la clase de prueba de Pest, que no existe en el proceso hijo.
*/

function baseDeDatosDelHijo(): Closure
{
    return fn () => DB::connection()->getDatabaseName();
}

/**
 * Tarea para un proceso hijo: espera la barrera de tiempo y dispensa.
 *
 * @param  list<array{int, int}>  $items  pares [prescripcion_item_id, cantidad]
 * @return Closure(): array{ok: bool, id?: int, repetida?: bool, codigo?: string}
 */
function tareaDispensar(float $barrera, int $usuarioId, int $pacienteId, int $prescripcionId, int $bodegaId, array $items, string $llave): Closure
{
    return function () use ($barrera, $usuarioId, $pacienteId, $prescripcionId, $bodegaId, $items, $llave) {
        // Todos los procesos arrancan a la vez para maximizar la contención.
        usleep(max(0, (int) (($barrera - microtime(true)) * 1_000_000)));

        try {
            $resultado = app(RegistrarDispensacion::class)->ejecutar(
                User::query()->findOrFail($usuarioId),
                new DatosDispensacion($pacienteId, $prescripcionId, $bodegaId, array_map(
                    fn (array $par) => ['prescripcion_item_id' => $par[0], 'cantidad' => $par[1]], $items,
                )),
                $llave,
            );

            return ['ok' => true, 'id' => $resultado->dispensacion->id, 'repetida' => $resultado->repetida];
        } catch (ReglaNegocioException $e) {
            return ['ok' => false, 'codigo' => $e->codigo];
        }
    };
}

function prescripcionCon(Producto ...$productos): Prescripcion
{
    $prescripcion = Prescripcion::factory()->create();
    foreach ($productos as $producto) {
        PrescripcionItem::factory()->create([
            'prescripcion_id' => $prescripcion->id, 'producto_id' => $producto->id, 'cantidad_prescrita' => 100,
        ]);
    }

    return $prescripcion;
}

function stockDe(Producto $producto, Bodega $bodega, int $cantidad): Existencia
{
    $lote = Lote::factory()->create(['producto_id' => $producto->id]);

    return Existencia::factory()->create([
        'bodega_id' => $bodega->id, 'lote_id' => $lote->id, 'producto_id' => $producto->id, 'cantidad' => $cantidad,
    ]);
}

it('los procesos hijos usan la base de pruebas', function () {
    expect(Concurrency::run([baseDeDatosDelHijo()]))->toBe(['farmart_test']);
});

it('nunca deja stock negativo con dispensaciones simultáneas (RN-03)', function () {
    $bodega = Bodega::factory()->create();
    $producto = Producto::factory()->create();
    $existencia = stockDe($producto, $bodega, 10);
    $barrera = microtime(true) + 3;

    // 6 pacientes distintos piden 3 unidades del mismo lote: solo caben 3 dispensaciones.
    $tareas = [];
    foreach (range(1, 6) as $i) {
        $prescripcion = prescripcionCon($producto);
        $tareas[] = tareaDispensar(
            $barrera, User::factory()->rol(Rol::AuxiliarFarmacia)->create()->id, $prescripcion->paciente_id,
            $prescripcion->id, $bodega->id, [[$prescripcion->items()->sole()->id, 3]], "llave-{$i}",
        );
    }

    $resultados = collect(Concurrency::run($tareas));

    expect($resultados->where('ok', true))->toHaveCount(3)
        ->and($resultados->where('ok', false)->pluck('codigo')->unique()->all())->toBe(['STOCK_INSUFICIENTE'])
        ->and($existencia->refresh()->cantidad)->toBe(1)
        ->and((int) MovimientoKardex::query()->sum('cantidad'))->toBe(-9)
        ->and(MovimientoKardex::query()->min('saldo_resultante'))->toBe(1);
});

it('crea una sola dispensación ante reintentos simultáneos con la misma llave (RN-09)', function () {
    $bodega = Bodega::factory()->create();
    $producto = Producto::factory()->create();
    $existencia = stockDe($producto, $bodega, 50);
    $prescripcion = prescripcionCon($producto);
    $usuario = User::factory()->rol(Rol::AuxiliarFarmacia)->create();
    $barrera = microtime(true) + 3;

    $tareas = array_map(fn () => tareaDispensar(
        $barrera, $usuario->id, $prescripcion->paciente_id, $prescripcion->id, $bodega->id,
        [[$prescripcion->items()->sole()->id, 5]], 'misma-llave',
    ), range(1, 5));

    $resultados = collect(Concurrency::run($tareas));

    expect($resultados->where('ok', true))->toHaveCount(5)
        ->and($resultados->pluck('id')->unique())->toHaveCount(1)
        ->and($resultados->where('repetida', false))->toHaveCount(1)
        ->and(Dispensacion::query()->count())->toBe(1)
        ->and($existencia->refresh()->cantidad)->toBe(45);
});

it('no se bloquea con pedidos de varios productos en orden cruzado', function () {
    $bodega = Bodega::factory()->create();
    [$a, $b] = [Producto::factory()->create(), Producto::factory()->create()];
    stockDe($a, $bodega, 100);
    stockDe($b, $bodega, 100);
    $barrera = microtime(true) + 3;

    // La mitad pide [A, B] y la otra mitad [B, A]: sin orden de bloqueo fijo habría deadlocks.
    $tareas = [];
    foreach (range(1, 6) as $i) {
        $prescripcion = prescripcionCon($a, $b);
        $items = $prescripcion->items()->orderBy('producto_id')->pluck('id')->map(fn (int $id) => [$id, 2])->all();
        $tareas[] = tareaDispensar(
            $barrera, User::factory()->rol(Rol::AuxiliarFarmacia)->create()->id, $prescripcion->paciente_id,
            $prescripcion->id, $bodega->id, $i % 2 === 0 ? $items : array_reverse($items), "llave-{$i}",
        );
    }

    $resultados = collect(Concurrency::run($tareas));

    expect($resultados->where('ok', true))->toHaveCount(6)
        ->and((int) Existencia::query()->sum('cantidad'))->toBe(200 - 6 * 4);
});
