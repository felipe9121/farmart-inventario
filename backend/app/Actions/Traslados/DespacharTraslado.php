<?php

declare(strict_types=1);

namespace App\Actions\Traslados;

use App\Enums\EstadoTraslado;
use App\Enums\TipoMovimiento;
use App\Exceptions\ReglaNegocioException;
use App\Models\Traslado;
use App\Models\TrasladoItem;
use App\Models\User;
use App\Services\Inventario\InventarioService;
use App\Support\FechaNegocio;
use App\Support\Transaccion;

/**
 * RN-07: APROBADO -> EN_TRANSITO. El stock sale del origen al despachar.
 */
final class DespacharTraslado
{
    use BloqueaTraslado;
    use OrdenaItemsTraslado;

    public function __construct(private readonly InventarioService $inventario) {}

    /**
     * @param  array<int, int>  $cantidades  traslado_item_id => cantidad a despachar; los ítems
     *                                       omitidos se despachan completos.
     */
    public function ejecutar(User $usuario, Traslado $traslado, array $cantidades = []): Traslado
    {
        return Transaccion::conReintento(function () use ($usuario, $traslado, $cantidades) {
            $traslado = $this->bloquearParaTransicion($traslado->id, EstadoTraslado::EnTransito);
            $items = $this->bloquearItems($traslado);

            $this->validarCantidades($items->all(), $cantidades);
            $total = 0;

            foreach ($this->ordenarParaBloqueo($traslado->bodega_origen_id, $items) as $item) {
                $cantidad = $cantidades[$item->id] ?? $item->cantidad_solicitada;
                $total += $cantidad;

                if ($cantidad > 0) {
                    // RN-01: pudo vencerse entre la solicitud y el despacho.
                    if ($item->lote->estaVencido(FechaNegocio::hoy())) {
                        throw new ReglaNegocioException('LOTE_VENCIDO', 'No se pueden trasladar lotes vencidos.', ['lote_id' => $item->lote_id]);
                    }

                    $existencia = $this->inventario->bloquearExistencia($traslado->bodega_origen_id, $item->lote_id)
                        ?? throw new ReglaNegocioException('STOCK_INSUFICIENTE', 'El origen no tiene stock del lote.', [
                            'lote_id' => $item->lote_id, 'solicitado' => $cantidad, 'disponible' => 0,
                        ]);

                    $this->inventario->registrarSalida($existencia, $cantidad, TipoMovimiento::TrasladoSalida, $usuario, [
                        'traslado_item_id' => $item->id,
                    ]);
                }

                $item->cantidad_despachada = $cantidad;
                $item->save();
            }

            if ($total === 0) {
                throw new ReglaNegocioException('DESPACHO_VACIO', 'El despacho debe incluir al menos una unidad.');
            }

            $traslado->estado = EstadoTraslado::EnTransito;
            $traslado->despachado_por = $usuario->id;
            $traslado->despachado_at = now();
            $traslado->save();

            return $traslado;
        });
    }

    /**
     * @param  list<TrasladoItem>  $items
     * @param  array<int, int>  $cantidades
     */
    private function validarCantidades(array $items, array $cantidades): void
    {
        $porId = collect($items)->keyBy('id');

        foreach ($cantidades as $itemId => $cantidad) {
            $item = $porId->get($itemId)
                ?? throw new ReglaNegocioException('ITEM_NO_PERTENECE_AL_TRASLADO', 'Un ítem no pertenece al traslado.', ['traslado_item_id' => $itemId]);

            if ($cantidad < 0 || $cantidad > $item->cantidad_solicitada) {
                throw new ReglaNegocioException('EXCEDE_LO_SOLICITADO', 'La cantidad a despachar debe estar entre 0 y lo solicitado.', [
                    'traslado_item_id' => $item->id, 'solicitado' => $item->cantidad_solicitada, 'despachar' => $cantidad,
                ]);
            }
        }
    }
}
