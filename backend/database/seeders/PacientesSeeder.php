<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Rol;
use App\Models\Paciente;
use App\Models\Prescripcion;
use App\Models\PrescripcionItem;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Pacientes y prescripciones sintéticos (documentos con prefijo 9 para que no
 * coincidan con personas reales).
 */
class PacientesSeeder extends Seeder
{
    public function run(): void
    {
        $medico = User::query()->where('rol', Rol::Medico)->firstOrFail();
        $productos = Producto::query()->orderBy('id')->get();
        $controlEspecial = $productos->firstWhere('es_control_especial', true);
        $comunes = $productos->where('es_control_especial', false)->values();

        Paciente::factory()->count(8)->create()->each(function (Paciente $paciente, int $i) use ($medico, $comunes, $controlEspecial) {
            $prescripcion = Prescripcion::factory()->create([
                'numero' => sprintf('RX-%06d', $i + 1),
                'paciente_id' => $paciente->id,
                'medico_id' => $medico->id,
            ]);

            foreach ($comunes->slice($i % 4, 2) as $producto) {
                PrescripcionItem::factory()->create([
                    'prescripcion_id' => $prescripcion->id,
                    'producto_id' => $producto->id,
                ]);
            }

            // Algunas prescripciones incluyen un producto de control especial (RN-05).
            if ($i % 3 === 0 && $controlEspecial !== null) {
                PrescripcionItem::factory()->create([
                    'prescripcion_id' => $prescripcion->id,
                    'producto_id' => $controlEspecial->id,
                    'cantidad_prescrita' => 10,
                ]);
            }
        });

        // Prescripción vencida: debe rechazarse al dispensar (RN-04).
        Prescripcion::factory()->vencida()->create([
            'numero' => 'RX-VENCIDA',
            'medico_id' => $medico->id,
        ]);
    }
}
