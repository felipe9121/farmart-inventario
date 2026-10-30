<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Producto>
 */
class ProductoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'codigo' => 'MED-'.fake()->unique()->numerify('######'),
            'nombre' => fake()->randomElement(['Acetaminofén', 'Ibuprofeno', 'Amoxicilina', 'Losartán', 'Metformina'])
                .' '.fake()->randomElement(['500 mg', '400 mg', '50 mg', '850 mg']),
            'presentacion' => fake()->randomElement(['Tableta', 'Cápsula', 'Jarabe', 'Ampolla']),
            'unidad_medida' => 'unidad',
            'es_control_especial' => false,
            'activo' => true,
        ];
    }

    public function controlEspecial(): static
    {
        return $this->state(fn (array $attributes) => ['es_control_especial' => true]);
    }
}
