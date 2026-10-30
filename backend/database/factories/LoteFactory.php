<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Lote;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lote>
 */
class LoteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'producto_id' => Producto::factory(),
            'numero_lote' => fake()->unique()->bothify('L-####??'),
            'fecha_vencimiento' => now()->addMonths(fake()->numberBetween(6, 36))->toDateString(),
        ];
    }

    public function venceEnDias(int $dias): static
    {
        return $this->state(fn (array $attributes) => [
            'fecha_vencimiento' => now()->addDays($dias)->toDateString(),
        ]);
    }

    public function vencido(): static
    {
        return $this->venceEnDias(-1);
    }
}
