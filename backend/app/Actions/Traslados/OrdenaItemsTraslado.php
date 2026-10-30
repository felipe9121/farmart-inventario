<?php

declare(strict_types=1);

namespace App\Actions\Traslados;

use App\Models\Traslado;
use App\Models\TrasladoItem;
use App\Services\Inventario\InventarioService;
use Illuminate\Database\Eloquent\Collection;

/**
 * @property-read InventarioService $inventario
 */
trait OrdenaItemsTraslado
{
    /**
     * @return Collection<int, TrasladoItem>
     */
    private function bloquearItems(Traslado $traslado): Collection
    {
        return TrasladoItem::query()
            ->with('lote')
            ->where('traslado_id', $traslado->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /**
     * Orden global de bloqueo de existencias: producto_id y luego id de la existencia.
     *
     * @param  Collection<int, TrasladoItem>  $items
     * @return list<TrasladoItem>
     */
    private function ordenarParaBloqueo(int $bodegaId, Collection $items): array
    {
        $rangos = $this->inventario->rangoDeBloqueo($bodegaId, $items->pluck('lote_id')->all());

        return $items
            ->sortBy(fn (TrasladoItem $item) => [$item->lote->producto_id, $rangos[$item->lote_id], $item->id])
            ->values()
            ->all();
    }
}
