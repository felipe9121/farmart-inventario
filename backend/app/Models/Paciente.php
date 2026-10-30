<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoDocumento;
use Database\Factories\PacienteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Datos sensibles (RN-10): nunca registrar estos atributos en logs.
 *
 * @property int $id
 * @property TipoDocumento $tipo_documento
 * @property string $numero_documento
 * @property string $nombres
 * @property string $apellidos
 * @property Carbon $fecha_nacimiento
 * @property string|null $telefono
 */
#[Table('pacientes')]
#[Fillable(['tipo_documento', 'numero_documento', 'nombres', 'apellidos', 'fecha_nacimiento', 'telefono'])]
class Paciente extends Model
{
    /** @use HasFactory<PacienteFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'tipo_documento' => TipoDocumento::class,
            'fecha_nacimiento' => 'date',
        ];
    }

    /** @return HasMany<Prescripcion, $this> */
    public function prescripciones(): HasMany
    {
        return $this->hasMany(Prescripcion::class);
    }
}
