<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Acciones registradas en la bitácora de accesos (RN-10). Deben coincidir con ck_bitacora_accesos_accion.
 */
enum AccionBitacora: string
{
    case Busqueda = 'BUSQUEDA';
    case ConsultaDetalle = 'CONSULTA_DETALLE';
    case ConsultaPrescripciones = 'CONSULTA_PRESCRIPCIONES';
    case Dispensacion = 'DISPENSACION';
}
