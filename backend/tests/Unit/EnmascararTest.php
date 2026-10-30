<?php

declare(strict_types=1);

use App\Support\Enmascarar;

it('deja solo la inicial de cada nombre', function () {
    expect(Enmascarar::nombre('María José'))->toBe('M*** J***')
        ->and(Enmascarar::nombre('  Ñuñez   Ávila '))->toBe('Ñ*** Á***');
});

it('deja visibles solo los últimos caracteres', function () {
    expect(Enmascarar::ultimos('9012345678'))->toBe('******5678')
        ->and(Enmascarar::ultimos('3001234567', 2))->toBe('********67')
        ->and(Enmascarar::ultimos('123'))->toBe('**3')
        ->and(Enmascarar::ultimos(null))->toBeNull();
});

it('deja solo el año de una fecha', function () {
    expect(Enmascarar::fecha('1985-03-14'))->toBe('1985-**-**');
});
