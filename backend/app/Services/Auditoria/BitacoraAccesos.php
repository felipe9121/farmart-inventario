<?php

declare(strict_types=1);

namespace App\Services\Auditoria;

use App\Enums\AccionBitacora;
use App\Models\BitacoraAccesoPaciente;
use App\Models\User;

/**
 * RN-10: registra quién accedió a datos de qué paciente. Solo IDs, rol, acción e IP:
 * nunca datos personales ni el término buscado.
 */
class BitacoraAccesos
{
    public function registrar(User $usuario, AccionBitacora $accion, ?int $pacienteId, ?string $ip): void
    {
        BitacoraAccesoPaciente::query()->create([
            'usuario_id' => $usuario->id,
            'rol' => $usuario->rol,
            'paciente_id' => $pacienteId,
            'accion' => $accion,
            'ip' => $ip,
        ]);
    }
}
