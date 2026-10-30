<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Traslado;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Traslado
 */
class TrasladoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'estado' => $this->estado->value,
            'bodega_origen_id' => $this->bodega_origen_id,
            'bodega_destino_id' => $this->bodega_destino_id,
            'creado_por' => $this->creado_por,
            'solicitado_por' => $this->solicitado_por,
            'solicitado_at' => $this->solicitado_at?->toIso8601String(),
            'aprobado_por' => $this->aprobado_por,
            'aprobado_at' => $this->aprobado_at?->toIso8601String(),
            'despachado_por' => $this->despachado_por,
            'despachado_at' => $this->despachado_at?->toIso8601String(),
            'recibido_por' => $this->recibido_por,
            'recibido_at' => $this->recibido_at?->toIso8601String(),
            'anulado_por' => $this->anulado_por,
            'anulado_at' => $this->anulado_at?->toIso8601String(),
            'motivo_anulacion' => $this->motivo_anulacion,
            'observaciones' => $this->observaciones,
            'created_at' => $this->created_at?->toIso8601String(),
            'items' => TrasladoItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
