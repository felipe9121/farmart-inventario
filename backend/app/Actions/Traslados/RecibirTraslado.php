<?php

declare(strict_types=1);

namespace App\Actions\Traslados;

use App\Enums\EstadoTraslado;
use App\Enums\TipoMovimiento;
use App\Exceptions\ReglaNegocioException;
use App\Models\Traslado;
use App\Models\User;
use App\Services\Inventario\InventarioService;
use App\Support\Transaccion;

/**
 * RN-07: EN_TRANSITO -> RECIBIDO | RECIBIDO_PARCIAL. El stock entra al destino al recibir;
 * lo despachado y no recibido queda como discrepancia pendiente (columna calculada).
 */
final class RecibirTraslado
{
    use BloqueaTraslado;
    use OrdenaItemsTraslado;

    public function __construct(private readonly InventarioService $inventario) {}

    /**
     * @param  array<int, int>  $recibidas  traslado_item_id => cantidad recibida; deben venir todos los ítems.
     */
    public function ejecutar(User $usuario, Traslado $traslado, array $recibidas): Traslado
    {
        return Transaccion::conReintento(function () use ($usuario, $traslado, $recibidas) {
            $traslado = $this->bloquearParaTransicion($traslado->id, EstadoTraslado::Recibido);
            $items = $this->bloquearItems($traslado);

            $ajenos = array_diff(array_keys($recibidas), $items->pluck('id')->all());
            if ($ajenos !== []) {
                throw new ReglaNegocioException('ITEM_NO_PERTENECE_AL_TRASLADO', 'Un ítem no pertenece al traslado.', [
                    'traslado_item_id' => reset($ajenos),
                ]);
            }

            $completo = true;

            foreach ($this->ordenarParaBloqueo($traslado->bodega_destino_id, $items) as $item) {
                // Se exige informar cada ítem, aunque sea 0: la recepción es un conteo explícito.
                if (! array_key_exists($item->id, $recibidas)) {
                    throw new ReglaNegocioException('RECEPCION_INCOMPLETA', 'Debe informarse la cantidad recibida de cada ítem.', [
                        'traslado_item_id' => $item->id,
                    ]);
                }

                $recibida = $recibidas[$item->id];
                $despachada = (int) $item->cantidad_despachada;

                if ($recibida < 0 || $recibida > $despachada) {
                    throw new ReglaNegocioException('EXCEDE_LO_DESPACHADO', 'La cantidad recibida debe estar entre 0 y lo despachado.', [
                        'traslado_item_id' => $item->id, 'despachado' => $despachada, 'recibido' => $recibida,
                    ]);
                }

                if ($recibida > 0) {
                    $this->inventario->registrarEntrada($traslado->bodega_destino_id, $item->lote, $recibida, TipoMovimiento::TrasladoEntrada, $usuario, [
                        'traslado_item_id' => $item->id,
                    ]);
                }

                $item->cantidad_recibida = $recibida;
                $item->save();
                $completo = $completo && $recibida === $despachada;
            }

            $traslado->estado = $completo ? EstadoTraslado::Recibido : EstadoTraslado::RecibidoParcial;
            $traslado->recibido_por = $usuario->id;
            $traslado->recibido_at = now();
            $traslado->save();

            return $traslado;
        });
    }
}
