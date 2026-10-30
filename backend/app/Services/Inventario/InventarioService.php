<?php

declare(strict_types=1);

namespace App\Services\Inventario;

use App\Enums\TipoMovimiento;
use App\Exceptions\ReglaNegocioException;
use App\Models\Existencia;
use App\Models\MovimientoKardex;
use App\Models\User;
use App\Support\FechaNegocio;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Único punto que modifica stock. Cada cambio se hace con la fila bloqueada, dentro de
 * la transacción del llamador, y genera su movimiento de kardex (RN-03, RN-06).
 *
 * Orden global de bloqueo para evitar deadlocks: los llamadores procesan los productos
 * por producto_id ascendente y aquí las existencias se bloquean por id ascendente.
 */
class InventarioService
{
    /**
     * RN-02: elige los lotes por FEFO (primero el que vence antes), puede repartir la
     * cantidad entre varios lotes y nunca usa lotes vencidos (RN-01).
     *
     * @return list<AsignacionLote>
     *
     * @throws ReglaNegocioException si no hay stock vigente suficiente.
     */
    public function asignarFefo(int $bodegaId, int $productoId, int $cantidad): array
    {
        $this->exigirTransaccion();

        // Se bloquean todas las candidatas en orden de id (UPDLOCK + HOLDLOCK: nadie puede
        // modificarlas ni insertar existencias nuevas del producto en la bodega) y luego
        // se ordenan por FEFO en memoria.
        $candidatas = Existencia::query()
            ->with('lote')
            ->where('bodega_id', $bodegaId)
            ->where('producto_id', $productoId)
            ->where('cantidad', '>', 0)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->reject(fn (Existencia $existencia) => $existencia->lote->estaVencido(FechaNegocio::hoy()))
            ->sortBy(fn (Existencia $existencia) => [$existencia->lote->fecha_vencimiento->toDateString(), $existencia->lote_id]);

        $disponible = (int) $candidatas->sum('cantidad');
        if ($disponible < $cantidad) {
            throw new ReglaNegocioException('STOCK_INSUFICIENTE', 'No hay stock vigente suficiente en la bodega.', [
                'producto_id' => $productoId,
                'solicitado' => $cantidad,
                'disponible' => $disponible,
            ]);
        }

        $asignaciones = [];
        $pendiente = $cantidad;
        foreach ($candidatas as $existencia) {
            $tomar = min($pendiente, $existencia->cantidad);
            $asignaciones[] = new AsignacionLote($existencia, $tomar);
            $pendiente -= $tomar;
            if ($pendiente === 0) {
                break;
            }
        }

        return $asignaciones;
    }

    /**
     * Descuenta stock de una existencia ya bloqueada y registra el movimiento.
     *
     * @param  array{dispensacion_item_id?: int, traslado_item_id?: int}  $referencia
     */
    public function registrarSalida(
        Existencia $existencia,
        int $cantidad,
        TipoMovimiento $tipo,
        User $usuario,
        array $referencia = [],
        ?string $motivo = null,
    ): MovimientoKardex {
        $this->exigirTransaccion();

        if (! $tipo->esSalida() || $cantidad <= 0) {
            throw new LogicException("Salida inválida: {$tipo->value} con cantidad {$cantidad}.");
        }

        $saldo = $existencia->cantidad - $cantidad;
        if ($saldo < 0) {
            // La BD lo rechazaría igual (ck_existencias_cantidad); se falla antes con un error claro.
            throw new ReglaNegocioException('STOCK_INSUFICIENTE', 'La existencia no tiene stock suficiente.', [
                'existencia_id' => $existencia->id,
                'solicitado' => $cantidad,
                'disponible' => $existencia->cantidad,
            ]);
        }

        $existencia->cantidad = $saldo;
        $existencia->save();

        return MovimientoKardex::query()->create([
            'bodega_id' => $existencia->bodega_id,
            'producto_id' => $existencia->producto_id,
            'lote_id' => $existencia->lote_id,
            'tipo' => $tipo,
            'cantidad' => -$cantidad,
            'saldo_resultante' => $saldo,
            'motivo' => $motivo,
            'dispensacion_item_id' => $referencia['dispensacion_item_id'] ?? null,
            'traslado_item_id' => $referencia['traslado_item_id'] ?? null,
            'usuario_id' => $usuario->id,
        ]);
    }

    private function exigirTransaccion(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Los cambios de stock deben ejecutarse dentro de una transacción.');
        }
    }
}
