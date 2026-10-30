<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Bodega;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bodega>
 */
class BodegaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'codigo' => 'BOD-'.fake()->unique()->numerify('####'),
            'nombre' => 'Bodega '.fake()->city(),
            'activa' => true,
        ];
    }
}
