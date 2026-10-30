<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Paciente;
use App\Support\Enmascarar;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Enmascara los datos personales si el usuario no tiene permiso de verlos completos (auditor).
 *
 * @mixin Paciente
 */
class PacienteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $completo = (bool) $request->user()?->can('verSinEnmascarar', Paciente::class);
        $nacimiento = $this->fecha_nacimiento->toDateString();

        return [
            'id' => $this->id,
            'tipo_documento' => $this->tipo_documento->value,
            'numero_documento' => $completo ? $this->numero_documento : Enmascarar::ultimos($this->numero_documento),
            'nombres' => $completo ? $this->nombres : Enmascarar::nombre($this->nombres),
            'apellidos' => $completo ? $this->apellidos : Enmascarar::nombre($this->apellidos),
            'fecha_nacimiento' => $completo ? $nacimiento : Enmascarar::fecha($nacimiento),
            'telefono' => $completo ? $this->telefono : Enmascarar::ultimos($this->telefono, 2),
            'enmascarado' => ! $completo,
        ];
    }
}
