<?php

declare(strict_types=1);

namespace App\Actions\Traslados;

use App\Enums\EstadoTraslado;
use App\Exceptions\ReglaNegocioException;
use App\Models\Traslado;

/**
 * Primer bloqueo de toda transición: el traslado. Orden global en traslados:
 * traslado -> ítems (id) -> existencias (producto_id, id), igual que en dispensación.
 */
trait BloqueaTraslado
{
    private function bloquearParaTransicion(int $trasladoId, EstadoTraslado $destino): Traslado
    {
        $traslado = Traslado::query()->lockForUpdate()->find($trasladoId)
            ?? throw new ReglaNegocioException('TRASLADO_NO_ENCONTRADO', 'El traslado no existe.', status: 404);

        // Se valida con la fila bloqueada: dos transiciones simultáneas se serializan y la
        // segunda ve el estado ya cambiado.
        if (! $traslado->estado->puedePasarA($destino)) {
            throw new ReglaNegocioException('TRANSICION_INVALIDA', 'El traslado no admite esa operación en su estado actual.', [
                'estado_actual' => $traslado->estado->value,
                'estado_destino' => $destino->value,
            ], status: 409);
        }

        return $traslado;
    }
}
