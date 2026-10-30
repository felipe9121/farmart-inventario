<?php

declare(strict_types=1);

namespace App\Actions\Traslados;

use App\Enums\EstadoTraslado;
use App\Models\Traslado;
use App\Models\User;
use App\Support\Transaccion;

/**
 * Solo antes del despacho (la máquina de estados y ck_traslados_anulacion lo garantizan),
 * así que anular nunca mueve stock.
 */
final class AnularTraslado
{
    use BloqueaTraslado;

    public function ejecutar(User $usuario, Traslado $traslado, string $motivo): Traslado
    {
        return Transaccion::conReintento(function () use ($usuario, $traslado, $motivo) {
            $traslado = $this->bloquearParaTransicion($traslado->id, EstadoTraslado::Anulado);

            $traslado->estado = EstadoTraslado::Anulado;
            $traslado->anulado_por = $usuario->id;
            $traslado->anulado_at = now();
            $traslado->motivo_anulacion = $motivo;
            $traslado->save();

            return $traslado;
        });
    }
}
