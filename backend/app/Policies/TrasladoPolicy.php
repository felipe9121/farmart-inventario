<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Rol;
use App\Models\Traslado;
use App\Models\User;

class TrasladoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->activo && $user->tieneRol(Rol::AuxiliarFarmacia, Rol::RegenteFarmacia, Rol::Auditor, Rol::Admin);
    }

    public function view(User $user, Traslado $traslado): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->operaFarmacia($user);
    }

    public function solicitar(User $user, Traslado $traslado): bool
    {
        return $this->operaFarmacia($user);
    }

    /**
     * Solo regentes. RN-08 (aprobador distinto del solicitante) se valida en la acción y en la BD.
     */
    public function aprobar(User $user, Traslado $traslado): bool
    {
        return $user->activo && $user->tieneRol(Rol::RegenteFarmacia);
    }

    public function despachar(User $user, Traslado $traslado): bool
    {
        return $this->operaFarmacia($user);
    }

    public function recibir(User $user, Traslado $traslado): bool
    {
        return $this->operaFarmacia($user);
    }

    /**
     * Un regente, o quien creó el traslado.
     */
    public function anular(User $user, Traslado $traslado): bool
    {
        return $this->operaFarmacia($user)
            && ($user->tieneRol(Rol::RegenteFarmacia) || $traslado->creado_por === $user->id);
    }

    private function operaFarmacia(User $user): bool
    {
        return $user->activo && $user->tieneRol(Rol::AuxiliarFarmacia, Rol::RegenteFarmacia);
    }
}
