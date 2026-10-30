<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TipoDocumento;
use App\Models\Paciente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Solo datos sintéticos.
 *
 * @extends Factory<Paciente>
 */
class PacienteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tipo_documento' => TipoDocumento::CedulaCiudadania,
            'numero_documento' => fake()->unique()->numerify('9#########'),
            'nombres' => fake()->firstName(),
            'apellidos' => fake()->lastName().' '.fake()->lastName(),
            'fecha_nacimiento' => fake()->dateTimeBetween('-90 years', '-1 year')->format('Y-m-d'),
            'telefono' => fake()->numerify('300#######'),
        ];
    }
}
