<?php

declare(strict_types=1);

use App\Enums\TipoDocumento;
use App\Models\Paciente;
use App\Models\Prescripcion;
use App\Models\PrescripcionItem;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('no duplica pacientes por tipo y número de documento', function () {
    Paciente::factory()->create(['tipo_documento' => TipoDocumento::CedulaCiudadania, 'numero_documento' => '900']);
    Paciente::factory()->create(['tipo_documento' => TipoDocumento::CedulaCiudadania, 'numero_documento' => '900']);
})->throws(QueryException::class, 'uq_pacientes_documento');

it('rechaza un tipo de documento no válido', function () {
    $paciente = Paciente::factory()->create();

    DB::table('pacientes')->where('id', $paciente->id)->update(['tipo_documento' => 'XX']);
})->throws(QueryException::class, 'ck_pacientes_tipo_documento');

it('rechaza una vigencia anterior a la emisión', function () {
    Prescripcion::factory()->create(['fecha_emision' => '2026-10-10', 'vigente_hasta' => '2026-10-09']);
})->throws(QueryException::class, 'ck_prescripciones_vigencia');

it('evalúa la vigencia con ambos extremos inclusive (RN-04)', function () {
    $prescripcion = Prescripcion::factory()->make(['fecha_emision' => '2026-10-01', 'vigente_hasta' => '2026-10-31']);

    expect($prescripcion->estaVigente(CarbonImmutable::parse('2026-09-30')))->toBeFalse()
        ->and($prescripcion->estaVigente(CarbonImmutable::parse('2026-10-01')))->toBeTrue()
        ->and($prescripcion->estaVigente(CarbonImmutable::parse('2026-10-31')))->toBeTrue()
        ->and($prescripcion->estaVigente(CarbonImmutable::parse('2026-11-01')))->toBeFalse();
});

it('rechaza cantidades prescritas no positivas', function () {
    PrescripcionItem::factory()->create(['cantidad_prescrita' => 0]);
})->throws(QueryException::class, 'ck_prescripcion_items_cantidad_prescrita');

it('acumula dispensaciones parciales hasta lo prescrito', function () {
    $item = PrescripcionItem::factory()->create(['cantidad_prescrita' => 10]);

    DB::table('prescripcion_items')->where('id', $item->id)->increment('cantidad_dispensada', 4);
    DB::table('prescripcion_items')->where('id', $item->id)->increment('cantidad_dispensada', 6);

    expect($item->fresh()->cantidadPendiente())->toBe(0);
});

it('impide en la BD dispensar más de lo prescrito (RN-04)', function () {
    $item = PrescripcionItem::factory()->create(['cantidad_prescrita' => 10]);

    DB::table('prescripcion_items')->where('id', $item->id)->increment('cantidad_dispensada', 11);
})->throws(QueryException::class, 'ck_prescripcion_items_cantidad_dispensada');

it('no repite un producto dentro de la misma prescripción', function () {
    $item = PrescripcionItem::factory()->create();

    PrescripcionItem::factory()->create(['prescripcion_id' => $item->prescripcion_id, 'producto_id' => $item->producto_id]);
})->throws(QueryException::class, 'uq_prescripcion_items_producto');
