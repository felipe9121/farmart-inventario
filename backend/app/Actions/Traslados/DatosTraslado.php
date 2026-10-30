<?php

declare(strict_types=1);

namespace App\Actions\Traslados;

final readonly class DatosTraslado
{
    /**
     * @param  list<array{lote_id: int, cantidad: int}>  $items
     */
    public function __construct(
        public int $bodegaOrigenId,
        public int $bodegaDestinoId,
        public array $items,
        public ?string $observaciones = null,
    ) {}
}
