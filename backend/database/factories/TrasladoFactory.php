<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EstadoTraslado;
use App\Enums\Rol;
use App\Models\Bodega;
use App\Models\Traslado;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Traslado>
 */
class TrasladoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'codigo' => 'TR-'.fake()->unique()->numerify('########'),
            'bodega_origen_id' => Bodega::factory(),
            'bodega_destino_id' => Bodega::factory(),
            'estado' => EstadoTraslado::Borrador,
            'creado_por' => User::factory()->rol(Rol::AuxiliarFarmacia),
        ];
    }

    public function solicitado(?User $solicitante = null): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => EstadoTraslado::Solicitado,
            'solicitado_por' => $solicitante ?? User::factory()->rol(Rol::AuxiliarFarmacia),
            'solicitado_at' => now(),
        ]);
    }

    public function aprobado(?User $aprobador = null): static
    {
        return $this->solicitado()->state(fn (array $attributes) => [
            'estado' => EstadoTraslado::Aprobado,
            'aprobado_por' => $aprobador ?? User::factory()->rol(Rol::RegenteFarmacia),
            'aprobado_at' => now(),
        ]);
    }
}
