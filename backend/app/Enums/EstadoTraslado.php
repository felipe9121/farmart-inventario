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

    /**
     * Solo se anula antes del despacho: después el stock ya salió del origen.
     *
     * @return list<self>
     */
    public function siguientes(): array
    {
        return match ($this) {
            self::Borrador => [self::Solicitado, self::Anulado],
            self::Solicitado => [self::Aprobado, self::Anulado],
            self::Aprobado => [self::EnTransito, self::Anulado],
            self::EnTransito => [self::Recibido, self::RecibidoParcial],
            self::Recibido, self::RecibidoParcial, self::Anulado => [],
        };
    }

    public function puedePasarA(self $destino): bool
    {
        return in_array($destino, $this->siguientes(), true);
    }

    public function esFinal(): bool
    {
        return $this->siguientes() === [];
    }
}
