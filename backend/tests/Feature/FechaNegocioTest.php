<?php

declare(strict_types=1);

use App\Models\Lote;
use App\Support\FechaNegocio;
use Carbon\CarbonImmutable;

afterEach(fn () => CarbonImmutable::setTestNow());

it('usa el día de Colombia aunque en UTC ya sea el día siguiente', function () {
    // 29-oct 21:00 en Bogotá = 30-oct 02:00 UTC.
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-30 02:00:00', 'UTC'));

    expect(FechaNegocio::hoy()->toDateString())->toBe('2026-10-29');
});

it('no considera vencido un lote que vence hoy en la noche colombiana', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-30 02:00:00', 'UTC'));
    $lote = Lote::factory()->make(['fecha_vencimiento' => '2026-10-29']);

    expect($lote->estaVencido())->toBeFalse();
});
