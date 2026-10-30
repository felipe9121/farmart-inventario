<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\FechaNegocio;
use Carbon\CarbonImmutable;
use Database\Factories\PrescripcionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $numero
 * @property int $paciente_id
 * @property int $medico_id
 * @property Carbon $fecha_emision
 * @property Carbon $vigente_hasta
 * @property string|null $observaciones
 */
#[Table('prescripciones')]
#[Fillable(['numero', 'paciente_id', 'medico_id', 'fecha_emision', 'vigente_hasta', 'observaciones'])]
class Prescripcion extends Model
{
    /** @use HasFactory<PrescripcionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'fecha_emision' => 'date',
            'vigente_hasta' => 'date',
        ];
    }

    /** @return BelongsTo<Paciente, $this> */
    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class);
    }

    /** @return BelongsTo<User, $this> */
    public function medico(): BelongsTo
    {
        return $this->belongsTo(User::class, 'medico_id');
    }

    /** @return HasMany<PrescripcionItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PrescripcionItem::class);
    }

    /**
     * RN-04: vigente entre la fecha de emisión y vigente_hasta, ambas inclusive.
     */
    public function estaVigente(?CarbonImmutable $hoy = null): bool
    {
        $hoy = ($hoy ?? FechaNegocio::hoy())->toDateString();

        return $this->fecha_emision->toDateString() <= $hoy && $this->vigente_hasta->toDateString() >= $hoy;
    }

    /** @return HasMany<Dispensacion, $this> */
    public function dispensaciones(): HasMany
    {
        return $this->hasMany(Dispensacion::class);
    }
}
