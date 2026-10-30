<?php

declare(strict_types=1);

use App\Services\Asistente\Intencion;
use App\Services\Asistente\Interpretacion;
use App\Services\Asistente\ModeloLenguajeSimulado;

const BODEGAS_DE_PRUEBA_ASISTENTE = [
    ['id' => 1, 'codigo' => 'BOD-CEN', 'nombre' => 'Bodega Central'],
    ['id' => 2, 'codigo' => 'FAR-NOR', 'nombre' => 'Farmacia Sede Norte'],
];

function interpretar(string $pregunta): Interpretacion
{
    return (new ModeloLenguajeSimulado)->interpretar($pregunta, BODEGAS_DE_PRUEBA_ASISTENTE);
}

it('reconoce la pregunta de vencimientos', function (string $pregunta) {
    expect(interpretar($pregunta)->intencion)->toBe(Intencion::LotesPorVencer);
})->with([
    '¿Qué lotes vencen pronto?',
    'lotes próximos a VENCER en la bodega central',
    'Muéstrame los productos que caducan este mes',
    '¿Hay medicamentos vencidos?',
]);

it('reconoce la pregunta de stock bajo el mínimo', function (string $pregunta) {
    expect(interpretar($pregunta)->intencion)->toBe(Intencion::StockBajoMinimo);
})->with([
    '¿Qué productos están bajo el stock mínimo?',
    'Qué tengo que reponer en FAR-NOR',
    'productos agotados en la sede norte',
    '¿Qué está por debajo del mínimo?',
]);

it('no adivina si la pregunta no corresponde o es ambigua', function (string $pregunta) {
    expect(interpretar($pregunta)->intencion)->toBe(Intencion::Desconocida);
})->with([
    '¿Cuál es el teléfono del paciente Juan?',
    'Hola',
    '¿Qué lotes vencen y qué está bajo el mínimo?',
]);

it('identifica la bodega por código o por nombre, sin importar tildes ni mayúsculas', function (string $pregunta, ?int $bodega) {
    expect(interpretar($pregunta)->bodegaId)->toBe($bodega);
})->with([
    ['lotes por vencer en BOD-CEN', 1],
    ['lotes por vencer en la bodega central', 1],
    ['¿qué vence en la Farmacia Sede Norte?', 2],
    ['¿qué vence en la sede norte?', 2],
    ['¿qué vence en todas las bodegas?', null],
]);

it('extrae el número de días del vencimiento', function (string $pregunta, ?int $dias) {
    expect(interpretar($pregunta)->dias)->toBe($dias);
})->with([
    ['lotes que vencen en 30 días', 30],
    ['lotes que vencen en los próximos 1 dia', 1],
    ['lotes que vencen en 900 días', 365],
    ['qué vence esta semana', 7],
    ['qué vence este mes', 30],
    ['qué lotes vencen', null],
]);
