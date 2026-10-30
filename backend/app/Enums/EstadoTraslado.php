<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estados de un traslado (RN-07). Deben coincidir con ck_traslados_estado.
 */
enum EstadoTraslado: string
{
    case Borrador = 'BORRADOR';
    case Solicitado = 'SOLICITADO';
    case Aprobado = 'APROBADO';
    case EnTransito = 'EN_TRANSITO';
    case Recibido = 'RECIBIDO';
    case RecibidoParcial = 'RECIBIDO_PARCIAL';
    case Anulado = 'ANULADO';
}
