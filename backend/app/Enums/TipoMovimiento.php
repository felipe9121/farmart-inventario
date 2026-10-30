<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tipos de movimiento de kardex. Deben coincidir con ck_movimientos_kardex_tipo.
 */
enum TipoMovimiento: string
{
    case Entrada = 'ENTRADA';
    case Dispensacion = 'DISPENSACION';
    case TrasladoSalida = 'TRASLADO_SALIDA';
    case TrasladoEntrada = 'TRASLADO_ENTRADA';
    case AjustePositivo = 'AJUSTE_POSITIVO';
    case AjusteNegativo = 'AJUSTE_NEGATIVO';

    public function esSalida(): bool
    {
        return in_array($this, [self::Dispensacion, self::TrasladoSalida, self::AjusteNegativo], true);
    }

    public function esAjuste(): bool
    {
        return in_array($this, [self::AjustePositivo, self::AjusteNegativo], true);
    }
}
