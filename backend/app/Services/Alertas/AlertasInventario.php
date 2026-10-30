<?php

declare(strict_types=1);

namespace App\Services\Alertas;

use App\Support\FechaNegocio;
use Illuminate\Support\Facades\DB;

/**
 * RN-11: alertas de inventario por bodega. Solo lectura.
 */
class AlertasInventario
{
    public const DIAS_VENCIMIENTO = 90;

    /**
     * Lotes con stock que vencen dentro de $dias (incluye los ya vencidos que aún tienen
     * stock, porque hay que darlos de baja). Ordenados por fecha de vencimiento.
     *
     * @return list<array{bodega_id: int, bodega: string, producto_id: int, producto: string, lote_id: int, numero_lote: string, fecha_vencimiento: string, dias_para_vencer: int, cantidad: int}>
     */
    public function lotesPorVencer(?int $bodegaId = null, int $dias = self::DIAS_VENCIMIENTO): array
    {
        $hoy = FechaNegocio::hoy()->toDateString();

        return DB::table('existencias as e')
            ->join('lotes as l', 'l.id', '=', 'e.lote_id')
            ->join('productos as p', 'p.id', '=', 'e.producto_id')
            ->join('bodegas as b', 'b.id', '=', 'e.bodega_id')
            ->where('e.cantidad', '>', 0)
            ->whereDate('l.fecha_vencimiento', '<=', FechaNegocio::hoy()->addDays($dias)->toDateString())
            ->when($bodegaId, fn ($q) => $q->where('e.bodega_id', $bodegaId))
            ->orderBy('l.fecha_vencimiento')
            ->orderBy('b.codigo')
            ->orderBy('l.id')
            ->select([
                'b.id as bodega_id', 'b.nombre as bodega', 'p.id as producto_id', 'p.nombre as producto',
                'l.id as lote_id', 'l.numero_lote', 'l.fecha_vencimiento', 'e.cantidad',
            ])
            ->selectRaw('DATEDIFF(day, CAST(? AS date), l.fecha_vencimiento) as dias_para_vencer', [$hoy])
            ->get()
            ->map(fn (object $fila) => [
                'bodega_id' => (int) $fila->bodega_id,
                'bodega' => (string) $fila->bodega,
                'producto_id' => (int) $fila->producto_id,
                'producto' => (string) $fila->producto,
                'lote_id' => (int) $fila->lote_id,
                'numero_lote' => (string) $fila->numero_lote,
                'fecha_vencimiento' => substr((string) $fila->fecha_vencimiento, 0, 10),
                'dias_para_vencer' => (int) $fila->dias_para_vencer,
                'cantidad' => (int) $fila->cantidad,
            ])
            ->values()
            ->all();
    }

    /**
     * Productos cuyo stock vigente (sin lotes vencidos, que no se pueden dispensar) está por
     * debajo del mínimo definido para la bodega.
     *
     * @return list<array{bodega_id: int, bodega: string, producto_id: int, producto: string, minimo: int, disponible: int, faltante: int}>
     */
    public function stockBajoMinimo(?int $bodegaId = null): array
    {
        $vigente = DB::table('existencias as e')
            ->join('lotes as l', 'l.id', '=', 'e.lote_id')
            ->whereDate('l.fecha_vencimiento', '>=', FechaNegocio::hoy()->toDateString())
            ->groupBy('e.bodega_id', 'e.producto_id')
            ->select('e.bodega_id', 'e.producto_id', DB::raw('SUM(e.cantidad) as disponible'));

        return DB::table('stock_minimos as m')
            ->join('productos as p', 'p.id', '=', 'm.producto_id')
            ->join('bodegas as b', 'b.id', '=', 'm.bodega_id')
            ->leftJoinSub($vigente, 'v', fn ($join) => $join->on('v.bodega_id', '=', 'm.bodega_id')->on('v.producto_id', '=', 'm.producto_id'))
            ->whereRaw('COALESCE(v.disponible, 0) < m.cantidad_minima')
            ->when($bodegaId, fn ($q) => $q->where('m.bodega_id', $bodegaId))
            ->orderBy('b.codigo')
            ->orderBy('p.nombre')
            ->get([
                'b.id as bodega_id', 'b.nombre as bodega', 'p.id as producto_id', 'p.nombre as producto',
                'm.cantidad_minima as minimo', DB::raw('COALESCE(v.disponible, 0) as disponible'),
            ])
            ->map(fn (object $fila) => [
                'bodega_id' => (int) $fila->bodega_id,
                'bodega' => (string) $fila->bodega,
                'producto_id' => (int) $fila->producto_id,
                'producto' => (string) $fila->producto,
                'minimo' => (int) $fila->minimo,
                'disponible' => (int) $fila->disponible,
                'faltante' => (int) $fila->minimo - (int) $fila->disponible,
            ])
            ->values()
            ->all();
    }
}
