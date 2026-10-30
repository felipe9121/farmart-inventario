<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Rol;
use App\Models\User;

class MovimientoKardexPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->activo && $user->tieneRol(Rol::AuxiliarFarmacia, Rol::RegenteFarmacia, Rol::Auditor, Rol::Admin);
    }

    /**
     * Ajustes de inventario: responsabilidad del regente de farmacia.
     */
    public function ajustar(User $user): bool
    {
        return $user->activo && $user->tieneRol(Rol::RegenteFarmacia);
    }
}
