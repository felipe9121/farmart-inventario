<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Roles del sistema. Los valores deben coincidir con el CHECK ck_users_rol.
 */
enum Rol: string
{
    case AuxiliarFarmacia = 'auxiliar_farmacia';
    case RegenteFarmacia = 'regente_farmacia';
    case Medico = 'medico';
    case Auditor = 'auditor';
    case Admin = 'admin';
}
