<?php

declare(strict_types=1);

namespace App\Actions\Traslados;

use App\Enums\EstadoTraslado;
use App\Exceptions\ReglaNegocioException;
use App\Models\Traslado;
use App\Models\User;
use App\Support\Transaccion;

final class AprobarTraslado
{
    use BloqueaTraslado;

    public function ejecutar(User $aprobador, Traslado $traslado): Traslado
    {
        return Transaccion::conReintento(function () use ($aprobador, $traslado) {
            $traslado = $this->bloquearParaTransicion($traslado->id, EstadoTraslado::Aprobado);

            // RN-08 (la BD lo respalda con ck_traslados_aprobador_distinto).
            if ($traslado->solicitado_por === $aprobador->id) {
                throw new ReglaNegocioException('APROBADOR_ES_SOLICITANTE', 'Quien solicita un traslado no puede aprobarlo.', status: 403);
            }

            $traslado->estado = EstadoTraslado::Aprobado;
            $traslado->aprobado_por = $aprobador->id;
            $traslado->aprobado_at = now();
            $traslado->save();

            return $traslado;
        });
    }
}
