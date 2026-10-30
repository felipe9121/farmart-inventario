<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\QueryException;

/**
 * RN-10: el mensaje de un QueryException trae los valores de la consulta y SQL Server
 * repite los datos en conflicto ("The duplicate key value is (CC, 9012345678)"). Para el
 * log se conserva solo lo necesario para diagnosticar, sin valores.
 */
final class ErrorBaseDatos
{
    /**
     * @return array{sqlstate: string|null, codigo_driver: int|string|null, restriccion: string|null, sql: string, conexion: string}
     */
    public static function contextoSeguro(QueryException $e): array
    {
        $info = $e->errorInfo ?? [];
        $mensaje = (string) ($info[2] ?? $e->getPrevious()?->getMessage() ?? '');

        // Nombre de la restricción o índice violado (ck_, fk_, uq_...), que no es un dato personal.
        $restriccion = preg_match('/(?:constraint|index)\s+["\']([\w.]+)["\']/i', $mensaje, $m) === 1 ? $m[1] : null;

        return [
            'sqlstate' => isset($info[0]) ? (string) $info[0] : null,
            'codigo_driver' => $info[1] ?? null,
            'restriccion' => $restriccion,
            // Con placeholders (?): sin los valores de los bindings.
            'sql' => $e->getSql(),
            'conexion' => $e->connectionName,
        ];
    }
}
