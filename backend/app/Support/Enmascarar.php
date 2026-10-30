<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Enmascarado de datos personales para el rol auditor (RN-10): conserva lo justo para
 * reconocer un registro sin exponerlo.
 */
final class Enmascarar
{
    /** "María José" -> "M*** J***" */
    public static function nombre(string $valor): string
    {
        $partes = preg_split('/\s+/u', trim($valor), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return implode(' ', array_map(fn (string $parte) => mb_substr($parte, 0, 1).'***', $partes));
    }

    /** "9012345678" -> "******5678": solo los últimos 4 caracteres. */
    public static function ultimos(?string $valor, int $visibles = 4): ?string
    {
        if ($valor === null || $valor === '') {
            return $valor;
        }

        $largo = mb_strlen($valor);
        $visibles = min($visibles, intdiv($largo, 2));

        return str_repeat('*', $largo - $visibles).mb_substr($valor, $largo - $visibles);
    }

    /** "1985-03-14" -> "1985-**-**" */
    public static function fecha(string $fecha): string
    {
        return substr($fecha, 0, 4).'-**-**';
    }
}
