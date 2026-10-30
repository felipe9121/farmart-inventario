<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Rol;
use App\Models\User;

/**
 * Alertas de inventario (RN-11) y asistente: datos de inventario, sin datos de pacientes.
 */
final class AlertaPolicy
{
    public static function puedeVer(User $user): bool
    {
        return $user->activo && $user->tieneRol(Rol::AuxiliarFarmacia, Rol::RegenteFarmacia, Rol::Auditor, Rol::Admin);
    }
}
