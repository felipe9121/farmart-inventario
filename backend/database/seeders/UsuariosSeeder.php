<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsuariosSeeder extends Seeder
{
    public function run(): void
    {
        // Clave pública de demostración (no es un secreto): solo existe en entornos locales.
        $password = Hash::make('password');

        // Dos regentes: RN-05 exige un segundo regente que autorice y RN-08 que
        // apruebe alguien distinto a quien solicita.
        $usuarios = [
            ['Administrador Demo', 'admin@farmart.test', Rol::Admin],
            ['Regente Uno', 'regente1@farmart.test', Rol::RegenteFarmacia],
            ['Regente Dos', 'regente2@farmart.test', Rol::RegenteFarmacia],
            ['Auxiliar Uno', 'auxiliar1@farmart.test', Rol::AuxiliarFarmacia],
            ['Auxiliar Dos', 'auxiliar2@farmart.test', Rol::AuxiliarFarmacia],
            ['Médico Demo', 'medico@farmart.test', Rol::Medico],
            ['Auditor Demo', 'auditor@farmart.test', Rol::Auditor],
        ];

        foreach ($usuarios as [$nombre, $email, $rol]) {
            User::factory()->rol($rol)->create([
                'name' => $nombre,
                'email' => $email,
                'password' => $password,
            ]);
        }
    }
}
