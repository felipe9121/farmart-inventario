<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Datos sintéticos para desarrollo y demostración. Uso: php artisan migrate:fresh --seed
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Los seeders de demostración no se ejecutan en producción.');
        }

        // Idempotente: el contenedor migrate puede ejecutarse en cada arranque.
        if (User::query()->exists()) {
            $this->command->info('La base ya tiene datos: no se cargan los de demostración.');

            return;
        }

        $this->call([
            UsuariosSeeder::class,
            CatalogosSeeder::class,
            InventarioInicialSeeder::class,
            PacientesSeeder::class,
        ]);
    }
}
