<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AccionBitacora;
use App\Enums\Rol;
use App\Models\BitacoraAccesoPaciente;
use App\Models\Paciente;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BitacoraAccesoPaciente>
 */
class BitacoraAccesoPacienteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'usuario_id' => User::factory(),
            'rol' => Rol::AuxiliarFarmacia,
            'paciente_id' => Paciente::factory(),
            'accion' => AccionBitacora::ConsultaDetalle,
            'ip' => fake()->ipv4(),
        ];
    }
}
