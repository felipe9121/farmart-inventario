<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\BitacoraAccesoPaciente;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BitacoraAccesoPaciente
 */
class BitacoraAccesoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'usuario_id' => $this->usuario_id,
            'rol' => $this->rol->value,
            'paciente_id' => $this->paciente_id,
            'accion' => $this->accion->value,
            'ip' => $this->ip,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
