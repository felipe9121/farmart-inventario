<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DispensacionItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DispensacionItem
 */
class DispensacionItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'prescripcion_item_id' => $this->prescripcion_item_id,
            'cantidad' => $this->cantidad,
            'lote' => $this->whenLoaded('lote', fn () => [
                'id' => $this->lote->id,
                'producto_id' => $this->lote->producto_id,
                'numero_lote' => $this->lote->numero_lote,
                'fecha_vencimiento' => $this->lote->fecha_vencimiento->toDateString(),
            ]),
        ];
    }
}
