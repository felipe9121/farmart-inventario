<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Rol;
use App\Models\Paciente;
use App\Models\Prescripcion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Prescripcion>
 */
class PrescripcionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'numero' => 'RX-'.fake()->unique()->numerify('########'),
            'paciente_id' => Paciente::factory(),
            'medico_id' => User::factory()->rol(Rol::Medico),
            'fecha_emision' => now()->toDateString(),
            'vigente_hasta' => now()->addDays(30)->toDateString(),
        ];
    }

    public function vencida(): static
    {
        return $this->state(fn (array $attributes) => [
            'fecha_emision' => now()->subDays(60)->toDateString(),
            'vigente_hasta' => now()->subDay()->toDateString(),
        ]);
    }
}
