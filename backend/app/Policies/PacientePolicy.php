<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Rol;
use App\Models\Paciente;
use App\Models\User;

/**
 * RN-10: acceso a datos de pacientes por rol. El admin no accede (mínimo privilegio:
 * administra el sistema, no atiende pacientes). El auditor accede enmascarado.
 */
class PacientePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->activo && $user->tieneRol(Rol::AuxiliarFarmacia, Rol::RegenteFarmacia, Rol::Medico, Rol::Auditor);
    }

    public function view(User $user, Paciente $paciente): bool
    {
        return $this->viewAny($user);
    }

    public function verSinEnmascarar(User $user): bool
    {
        return $this->viewAny($user) && ! $user->tieneRol(Rol::Auditor);
    }
}
