<?php

declare(strict_types=1);

namespace App\Services\Inventario;

use App\Models\Existencia;

/**
 * Cantidad a tomar de una existencia (ya bloqueada) según FEFO.
 */
final readonly class AsignacionLote
{
    public function __construct(
        public Existencia $existencia,
        public int $cantidad,
    ) {}
}
