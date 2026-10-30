<?php

declare(strict_types=1);

namespace App\Actions\Dispensaciones;

use App\Models\Dispensacion;

final readonly class ResultadoDispensacion
{
    /**
     * @param  bool  $repetida  true si la llave de idempotencia ya existía y se devuelve la dispensación original.
     */
    public function __construct(
        public Dispensacion $dispensacion,
        public bool $repetida,
    ) {}
}
