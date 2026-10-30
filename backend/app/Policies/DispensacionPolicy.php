<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Rol;
use App\Models\Dispensacion;
use App\Models\User;

class DispensacionPolicy
{
    public function create(User $user): bool
    {
        return $user->activo && $user->tieneRol(Rol::AuxiliarFarmacia, Rol::RegenteFarmacia);
    }

    public function view(User $user, Dispensacion $dispensacion): bool
    {
        return $user->activo && $user->tieneRol(Rol::AuxiliarFarmacia, Rol::RegenteFarmacia, Rol::Auditor, Rol::Admin);
    }
}
