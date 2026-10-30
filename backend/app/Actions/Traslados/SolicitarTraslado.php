<?php

declare(strict_types=1);

namespace App\Actions\Traslados;

use App\Enums\EstadoTraslado;
use App\Models\Traslado;
use App\Models\User;
use App\Support\Transaccion;

final class SolicitarTraslado
{
    use BloqueaTraslado;

    public function ejecutar(User $usuario, Traslado $traslado): Traslado
    {
        return Transaccion::conReintento(function () use ($usuario, $traslado) {
            $traslado = $this->bloquearParaTransicion($traslado->id, EstadoTraslado::Solicitado);

            $traslado->estado = EstadoTraslado::Solicitado;
            $traslado->solicitado_por = $usuario->id;
            $traslado->solicitado_at = now();
            $traslado->save();

            return $traslado;
        });
    }
}
