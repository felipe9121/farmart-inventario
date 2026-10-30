<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Dispensacion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sin datos del paciente (RN-10): solo IDs.
 *
 * @mixin Dispensacion
 */
class DispensacionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'prescripcion_id' => $this->prescripcion_id,
            'bodega_id' => $this->bodega_id,
            'dispensado_por' => $this->dispensado_por,
            'autorizado_por' => $this->autorizado_por,
            'autorizado_at' => $this->autorizado_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'items' => DispensacionItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
