<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Rol;
use App\Models\Bodega;
use App\Models\Dispensacion;
use App\Models\Prescripcion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Dispensacion>
 */
class DispensacionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'prescripcion_id' => Prescripcion::factory(),
            'bodega_id' => Bodega::factory(),
            'dispensado_por' => User::factory()->rol(Rol::AuxiliarFarmacia),
            'idempotency_key' => (string) Str::uuid(),
            'idempotency_hash' => hash('sha256', Str::random()),
        ];
    }

    public function autorizadaPor(User $regente): static
    {
        return $this->state(fn (array $attributes) => [
            'autorizado_por' => $regente->id,
            'autorizado_at' => now(),
        ]);
    }
}
