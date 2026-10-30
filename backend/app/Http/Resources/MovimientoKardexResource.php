<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\MovimientoKardex;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MovimientoKardex
 */
class MovimientoKardexResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bodega_id' => $this->bodega_id,
            'producto_id' => $this->producto_id,
            'lote_id' => $this->lote_id,
            'tipo' => $this->tipo->value,
            'cantidad' => $this->cantidad,
            'saldo_resultante' => $this->saldo_resultante,
            'motivo' => $this->motivo,
            'documento_referencia' => $this->documento_referencia,
            'dispensacion_item_id' => $this->dispensacion_item_id,
            'traslado_item_id' => $this->traslado_item_id,
            'usuario_id' => $this->usuario_id,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
