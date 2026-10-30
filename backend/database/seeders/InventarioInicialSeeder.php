<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Rol;
use App\Enums\TipoMovimiento;
use App\Models\Bodega;
use App\Models\Existencia;
use App\Models\Lote;
use App\Models\MovimientoKardex;
use App\Models\Producto;
use App\Models\StockMinimo;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Stock inicial. Cada existencia entra con su movimiento ENTRADA en el kardex (RN-06).
 * Incluye a propósito lotes vencidos, lotes que vencen en <= 90 días y stock bajo
 * el mínimo, para poder demostrar FEFO y las alertas (RN-11).
 */
class InventarioInicialSeeder extends Seeder
{
    public function run(): void
    {
        $regente = User::query()->where('rol', Rol::RegenteFarmacia)->orderBy('id')->firstOrFail();
        $bodegas = Bodega::query()->orderBy('id')->get();

        DB::transaction(function () use ($regente, $bodegas) {
            foreach (Producto::query()->orderBy('id')->get() as $i => $producto) {
                $lotes = [
                    // Vencido: nunca debe dispensarse ni trasladarse (RN-01).
                    $this->lote($producto, 'V', -10),
                    // Próximo a vencer: debe salir primero por FEFO y generar alerta.
                    $this->lote($producto, 'P', 30 + $i * 5),
                    $this->lote($producto, 'A', 365 + $i * 10),
                ];

                foreach ($bodegas as $j => $bodega) {
                    foreach ($lotes as $k => $lote) {
                        $this->entrada($bodega, $lote, 20 + 15 * $k + 5 * $j, $regente);
                    }

                    // En la última bodega el mínimo queda por encima del stock para generar alerta.
                    StockMinimo::factory()->create([
                        'bodega_id' => $bodega->id,
                        'producto_id' => $producto->id,
                        'cantidad_minima' => $j === $bodegas->count() - 1 ? 500 : 30,
                    ]);
                }
            }
        });
    }

    private function lote(Producto $producto, string $sufijo, int $diasParaVencer): Lote
    {
        return Lote::factory()->for($producto)->venceEnDias($diasParaVencer)->create([
            'numero_lote' => sprintf('L%s-%s', substr($producto->codigo, -4), $sufijo),
        ]);
    }

    private function entrada(Bodega $bodega, Lote $lote, int $cantidad, User $usuario): void
    {
        Existencia::factory()->create([
            'bodega_id' => $bodega->id,
            'lote_id' => $lote->id,
            'producto_id' => $lote->producto_id,
            'cantidad' => $cantidad,
        ]);

        MovimientoKardex::factory()->create([
            'bodega_id' => $bodega->id,
            'lote_id' => $lote->id,
            'producto_id' => $lote->producto_id,
            'tipo' => TipoMovimiento::Entrada,
            'cantidad' => $cantidad,
            'saldo_resultante' => $cantidad,
            'documento_referencia' => 'INV-INICIAL',
            'usuario_id' => $usuario->id,
        ]);
    }
}
