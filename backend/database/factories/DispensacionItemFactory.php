<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Dispensacion;
use App\Models\DispensacionItem;
use App\Models\Lote;
use App\Models\PrescripcionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DispensacionItem>
 */
class DispensacionItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'dispensacion_id' => Dispensacion::factory(),
            'prescripcion_item_id' => PrescripcionItem::factory(),
            'lote_id' => Lote::factory(),
            'cantidad' => fake()->numberBetween(1, 10),
        ];
    }
}
