<?php

declare(strict_types=1);

namespace App\Actions\Inventario;

use App\Enums\TipoMovimiento;
use App\Exceptions\ReglaNegocioException;
use App\Models\Bodega;
use App\Models\Lote;
use App\Models\MovimientoKardex;
use App\Models\User;
use App\Services\Inventario\InventarioService;
use App\Support\Transaccion;

/**
 * RN-06: las correcciones de stock son ajustes con motivo, nunca ediciones del kardex.
 * Se permiten sobre lotes vencidos (p. ej. dar de baja vencidos), que igual no se dispensan.
 */
final class RegistrarAjuste
{
    public function __construct(private readonly InventarioService $inventario) {}

    /**
     * @param  int  $cantidad  con signo: positiva suma, negativa resta.
     */
    public function ejecutar(User $usuario, int $bodegaId, int $loteId, int $cantidad, string $motivo): MovimientoKardex
    {
        if ($cantidad === 0) {
            throw new ReglaNegocioException('AJUSTE_VACIO', 'La cantidad del ajuste no puede ser cero.');
        }

        if (! Bodega::query()->whereKey($bodegaId)->exists()) {
            throw new ReglaNegocioException('BODEGA_NO_DISPONIBLE', 'La bodega no existe.');
        }

        $lote = Lote::query()->find($loteId)
            ?? throw new ReglaNegocioException('LOTE_NO_ENCONTRADO', 'El lote no existe.', ['lote_id' => $loteId]);

        return Transaccion::conReintento(function () use ($usuario, $bodegaId, $lote, $cantidad, $motivo) {
            if ($cantidad > 0) {
                return $this->inventario->registrarEntrada($bodegaId, $lote, $cantidad, TipoMovimiento::AjustePositivo, $usuario, motivo: $motivo);
            }

            $existencia = $this->inventario->bloquearExistencia($bodegaId, $lote->id)
                ?? throw new ReglaNegocioException('STOCK_INSUFICIENTE', 'La bodega no tiene stock del lote.', [
                    'lote_id' => $lote->id, 'solicitado' => -$cantidad, 'disponible' => 0,
                ]);

            return $this->inventario->registrarSalida($existencia, -$cantidad, TipoMovimiento::AjusteNegativo, $usuario, motivo: $motivo);
        });
    }
}
