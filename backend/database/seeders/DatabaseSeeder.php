<?php

declare(strict_types=1);

namespace Database\Seeders;

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

        $this->call([
            UsuariosSeeder::class,
            CatalogosSeeder::class,
            InventarioInicialSeeder::class,
            PacientesSeeder::class,
        ]);
    }
}
