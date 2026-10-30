<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\TrasladoItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TrasladoItem
 */
class TrasladoItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cantidad_solicitada' => $this->cantidad_solicitada,
            'cantidad_despachada' => $this->cantidad_despachada,
            'cantidad_recibida' => $this->cantidad_recibida,
            // RN-07: despachado y no recibido (null mientras no se haya recibido).
            'cantidad_discrepancia' => $this->cantidad_discrepancia,
            'lote' => $this->whenLoaded('lote', fn () => [
                'id' => $this->lote->id,
                'producto_id' => $this->lote->producto_id,
                'numero_lote' => $this->lote->numero_lote,
                'fecha_vencimiento' => $this->lote->fecha_vencimiento->toDateString(),
            ]),
        ];
    }
}
