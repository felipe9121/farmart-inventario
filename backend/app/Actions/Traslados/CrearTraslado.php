<?php

declare(strict_types=1);

namespace App\Actions\Traslados;

use App\Exceptions\ReglaNegocioException;
use App\Models\Bodega;
use App\Models\Existencia;
use App\Models\Lote;
use App\Models\Traslado;
use App\Models\TrasladoItem;
use App\Models\User;
use App\Support\FechaNegocio;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Crea un traslado en BORRADOR. Cada ítem es un lote concreto del origen. No mueve stock:
 * la disponibilidad se revisa aquí como aviso temprano y de forma definitiva al despachar.
 */
final class CrearTraslado
{
    public function ejecutar(User $usuario, DatosTraslado $datos): Traslado
    {
        if ($datos->bodegaOrigenId === $datos->bodegaDestinoId) {
            throw new ReglaNegocioException('BODEGAS_IGUALES', 'El origen y el destino deben ser bodegas distintas.');
        }

        $bodegas = Bodega::query()->whereKey([$datos->bodegaOrigenId, $datos->bodegaDestinoId])->where('activa', true)->count();
        if ($bodegas !== 2) {
            throw new ReglaNegocioException('BODEGA_NO_DISPONIBLE', 'Alguna de las bodegas no existe o está inactiva.');
        }

        $lotes = Lote::query()->whereKey(array_column($datos->items, 'lote_id'))->get()->keyBy('id');

        foreach ($datos->items as $item) {
            $lote = $lotes->get($item['lote_id'])
                ?? throw new ReglaNegocioException('LOTE_NO_ENCONTRADO', 'El lote no existe.', ['lote_id' => $item['lote_id']]);

            // RN-01: un lote vencido nunca se traslada.
            if ($lote->estaVencido(FechaNegocio::hoy())) {
                throw new ReglaNegocioException('LOTE_VENCIDO', 'No se pueden trasladar lotes vencidos.', ['lote_id' => $lote->id]);
            }

            $disponible = (int) Existencia::query()
                ->where('bodega_id', $datos->bodegaOrigenId)
                ->where('lote_id', $lote->id)
                ->value('cantidad');

            if ($disponible < $item['cantidad']) {
                throw new ReglaNegocioException('STOCK_INSUFICIENTE', 'El origen no tiene stock suficiente del lote.', [
                    'lote_id' => $lote->id,
                    'solicitado' => $item['cantidad'],
                    'disponible' => $disponible,
                ]);
            }
        }

        return DB::transaction(function () use ($usuario, $datos) {
            $traslado = Traslado::query()->create([
                'codigo' => 'TR-'.Str::upper((string) Str::ulid()),
                'bodega_origen_id' => $datos->bodegaOrigenId,
                'bodega_destino_id' => $datos->bodegaDestinoId,
                'creado_por' => $usuario->id,
                'observaciones' => $datos->observaciones,
            ]);

            foreach ($datos->items as $item) {
                TrasladoItem::query()->create([
                    'traslado_id' => $traslado->id,
                    'lote_id' => $item['lote_id'],
                    'cantidad_solicitada' => $item['cantidad'],
                ]);
            }

            return $traslado->refresh();
        });
    }
}
