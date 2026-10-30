<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Prescripcion;
use App\Models\PrescripcionItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Prescripcion
 */
class PrescripcionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'numero' => $this->numero,
            'paciente_id' => $this->paciente_id,
            'medico_id' => $this->medico_id,
            'fecha_emision' => $this->fecha_emision->toDateString(),
            'vigente_hasta' => $this->vigente_hasta->toDateString(),
            'vigente' => $this->estaVigente(),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn (PrescripcionItem $item) => [
                'id' => $item->id,
                'producto_id' => $item->producto_id,
                'producto' => $item->producto->nombre,
                'es_control_especial' => $item->producto->es_control_especial,
                'cantidad_prescrita' => $item->cantidad_prescrita,
                'cantidad_dispensada' => $item->cantidad_dispensada,
                'cantidad_pendiente' => $item->cantidadPendiente(),
                'posologia' => $item->posologia,
            ])->all()),
        ];
    }
}
