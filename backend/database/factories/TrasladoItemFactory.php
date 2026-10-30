<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Lote;
use App\Models\Traslado;
use App\Models\TrasladoItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrasladoItem>
 */
class TrasladoItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'traslado_id' => Traslado::factory(),
            'lote_id' => Lote::factory(),
            'cantidad_solicitada' => fake()->numberBetween(5, 50),
        ];
    }
}
