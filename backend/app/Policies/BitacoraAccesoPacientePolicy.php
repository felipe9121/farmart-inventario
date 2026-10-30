<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Rol;
use App\Models\User;

class BitacoraAccesoPacientePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->activo && $user->tieneRol(Rol::Auditor, Rol::Admin);
    }
}
