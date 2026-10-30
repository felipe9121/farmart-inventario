<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TipoMovimiento;
use App\Models\Bodega;
use App\Models\Lote;
use App\Models\MovimientoKardex;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MovimientoKardex>
 */
class MovimientoKardexFactory extends Factory
{
    public function definition(): array
    {
        $cantidad = fake()->numberBetween(1, 100);

        return [
            'bodega_id' => Bodega::factory(),
            'lote_id' => Lote::factory(),
            'producto_id' => fn (array $attributes) => Lote::query()->findOrFail($attributes['lote_id'])->producto_id,
            'tipo' => TipoMovimiento::Entrada,
            'cantidad' => $cantidad,
            'saldo_resultante' => $cantidad,
            'documento_referencia' => 'FAC-'.fake()->numerify('#####'),
            'usuario_id' => User::factory(),
        ];
    }
}
