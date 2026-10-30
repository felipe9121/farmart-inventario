<?php

declare(strict_types=1);

use App\Actions\Traslados\DespacharTraslado;
use App\Actions\Traslados\SolicitarTraslado;
use App\Enums\EstadoTraslado;
use App\Enums\Rol;
use App\Models\Existencia;
use App\Models\MovimientoKardex;
use App\Models\Paciente;
use App\Models\Prescripcion;
use App\Models\PrescripcionItem;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Concurrency;
use Tests\Support\Concurrente;
use Tests\Support\EscenarioTraslado;

beforeEach(function () {
    Artisan::call('migrate:fresh');
});

it('solo una de varias aprobaciones simultáneas tiene éxito', function () {
    $e = EscenarioTraslado::crear();
    $traslado = app(SolicitarTraslado::class)->ejecutar($e->auxiliar, $e->borrador([[$e->stockEnOrigen(20), 5]]));
    $barrera = microtime(true) + 3;

    $tareas = array_map(
        fn () => Concurrente::aprobar($barrera, User::factory()->rol(Rol::RegenteFarmacia)->create()->id, $traslado->id),
        range(1, 4),
    );
    $resultados = collect(Concurrency::run($tareas));

    expect($resultados->where('ok', true))->toHaveCount(1)
        ->and($resultados->where('ok', false)->pluck('codigo')->unique()->all())->toBe(['TRANSICION_INVALIDA'])
        ->and($traslado->refresh()->estado)->toBe(EstadoTraslado::Aprobado);
});

it('un despacho y dispensaciones simultáneas sobre el mismo lote nunca sobregiran el stock', function () {
    $e = EscenarioTraslado::crear();
    $existencia = $e->stockEnOrigen(10);
    $traslado = $e->aprobado([[$existencia, 6]]);
    $barrera = microtime(true) + 3;

    // Junto al despacho de 6, cuatro dispensaciones de 3 en la bodega de origen.
    $tareas = [Concurrente::despachar($barrera, $e->auxiliar->id, $traslado->id)];
    foreach (range(1, 4) as $i) {
        $paciente = Paciente::factory()->create();
        $prescripcion = Prescripcion::factory()->create(['paciente_id' => $paciente->id]);
        $item = PrescripcionItem::factory()->create([
            'prescripcion_id' => $prescripcion->id, 'producto_id' => $existencia->producto_id, 'cantidad_prescrita' => 10,
        ]);
        $tareas[] = Concurrente::dispensar(
            $barrera, User::factory()->rol(Rol::AuxiliarFarmacia)->create()->id, $paciente->id,
            $prescripcion->id, $e->origen->id, [[$item->id, 3]], "llave-{$i}",
        );
    }

    $resultados = collect(Concurrency::run($tareas));
    $salido = -(int) MovimientoKardex::query()->sum('cantidad');

    expect($existencia->refresh()->cantidad)->toBeGreaterThanOrEqual(0)
        ->and($existencia->cantidad)->toBe(10 - $salido)
        ->and($salido)->toBeLessThanOrEqual(10)
        ->and($resultados->where('ok', false)->pluck('codigo')->unique()->values()->all())->toBe(['STOCK_INSUFICIENTE']);
});

it('dos recepciones simultáneas crean una sola existencia en el destino', function () {
    $e = EscenarioTraslado::crear();
    $existencia = $e->stockEnOrigen(20);
    $despachar = app(DespacharTraslado::class);
    $t1 = $despachar->ejecutar($e->auxiliar, $e->aprobado([[$existencia, 4]]));
    $t2 = $despachar->ejecutar($e->auxiliar, $e->aprobado([[$existencia, 6]]));
    $barrera = microtime(true) + 3;

    $resultados = collect(Concurrency::run([
        Concurrente::recibir($barrera, $e->auxiliar->id, $t1->id, [$t1->items()->sole()->id => 4]),
        Concurrente::recibir($barrera, $e->auxiliar->id, $t2->id, [$t2->items()->sole()->id => 6]),
    ]));

    $enDestino = Existencia::query()->where('bodega_id', $e->destino->id)->where('lote_id', $existencia->lote_id)->get();
    expect($resultados->where('ok', true))->toHaveCount(2)
        ->and($enDestino)->toHaveCount(1)
        ->and($enDestino->sole()->cantidad)->toBe(10);
});
