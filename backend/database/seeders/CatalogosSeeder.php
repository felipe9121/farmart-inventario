<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Bodega;
use App\Models\Producto;
use Illuminate\Database\Seeder;

class CatalogosSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['BOD-CEN', 'Bodega Central'],
            ['FAR-NOR', 'Farmacia Sede Norte'],
            ['FAR-SUR', 'Farmacia Sede Sur'],
        ] as [$codigo, $nombre]) {
            Bodega::factory()->create(['codigo' => $codigo, 'nombre' => $nombre]);
        }

        foreach ([
            ['MED-0001', 'Acetaminofén 500 mg', 'Tableta', false],
            ['MED-0002', 'Ibuprofeno 400 mg', 'Tableta', false],
            ['MED-0003', 'Amoxicilina 500 mg', 'Cápsula', false],
            ['MED-0004', 'Losartán 50 mg', 'Tableta', false],
            ['MED-0005', 'Metformina 850 mg', 'Tableta', false],
            ['MED-0006', 'Omeprazol 20 mg', 'Cápsula', false],
            ['MED-0007', 'Salbutamol 100 mcg', 'Inhalador', false],
            ['MED-0008', 'Clonazepam 2 mg', 'Tableta', true],
            ['MED-0009', 'Morfina 10 mg/mL', 'Ampolla', true],
            ['MED-0010', 'Tramadol 50 mg', 'Cápsula', true],
        ] as [$codigo, $nombre, $presentacion, $controlEspecial]) {
            Producto::factory()->create([
                'codigo' => $codigo,
                'nombre' => $nombre,
                'presentacion' => $presentacion,
                'es_control_especial' => $controlEspecial,
            ]);
        }
    }
}
