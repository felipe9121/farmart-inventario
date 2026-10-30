<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Bodega;
use App\Models\Existencia;
use App\Models\Lote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Existencia>
 */
class ExistenciaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'bodega_id' => Bodega::factory(),
            'lote_id' => Lote::factory(),
            // Siempre el producto del lote, como exige la FK compuesta.
            'producto_id' => fn (array $attributes) => Lote::query()->findOrFail($attributes['lote_id'])->producto_id,
            'cantidad' => fake()->numberBetween(10, 500),
        ];
    }
}
