<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tipos de documento de identidad. Deben coincidir con ck_pacientes_tipo_documento.
 */
enum TipoDocumento: string
{
    case CedulaCiudadania = 'CC';
    case TarjetaIdentidad = 'TI';
    case RegistroCivil = 'RC';
    case CedulaExtranjeria = 'CE';
    case Pasaporte = 'PA';
    case PermisoProteccionTemporal = 'PPT';
}
