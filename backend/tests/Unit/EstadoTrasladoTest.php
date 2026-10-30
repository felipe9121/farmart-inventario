<?php

declare(strict_types=1);

use App\Enums\EstadoTraslado as E;

it('sigue el flujo de RN-07', function (E $desde, E $hacia) {
    expect($desde->puedePasarA($hacia))->toBeTrue();
})->with([
    [E::Borrador, E::Solicitado],
    [E::Solicitado, E::Aprobado],
    [E::Aprobado, E::EnTransito],
    [E::EnTransito, E::Recibido],
    [E::EnTransito, E::RecibidoParcial],
]);

it('solo permite anular antes del despacho', function (E $estado, bool $anulable) {
    expect($estado->puedePasarA(E::Anulado))->toBe($anulable);
})->with([
    [E::Borrador, true],
    [E::Solicitado, true],
    [E::Aprobado, true],
    [E::EnTransito, false],
    [E::Recibido, false],
]);

it('no permite saltarse etapas ni volver atrás', function (E $desde, E $hacia) {
    expect($desde->puedePasarA($hacia))->toBeFalse();
})->with([
    [E::Borrador, E::Aprobado],
    [E::Solicitado, E::EnTransito],
    [E::Aprobado, E::Recibido],
    [E::EnTransito, E::Aprobado],
    [E::Aprobado, E::Solicitado],
]);

it('marca como finales los estados sin salida', function () {
    $finales = array_values(array_filter(E::cases(), fn (E $e) => $e->esFinal()));

    expect($finales)->toBe([E::Recibido, E::RecibidoParcial, E::Anulado]);
});
