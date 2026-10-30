<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\FechaNegocio;
use Carbon\CarbonImmutable;
use Database\Factories\LoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $producto_id
 * @property string $numero_lote
 * @property Carbon $fecha_vencimiento
 */
#[Table('lotes')]
#[Fillable(['producto_id', 'numero_lote', 'fecha_vencimiento'])]
class Lote extends Model
{
    /** @use HasFactory<LoteFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['fecha_vencimiento' => 'date'];
    }

    /** @return BelongsTo<Producto, $this> */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    /** @return HasMany<Existencia, $this> */
    public function existencias(): HasMany
    {
        return $this->hasMany(Existencia::class);
    }

    /**
     * RN-01: un lote vence al final de su fecha de vencimiento; ese mismo día aún es usable.
     */
    public function estaVencido(?CarbonImmutable $hoy = null): bool
    {
        return $this->fecha_vencimiento->toDateString() < ($hoy ?? FechaNegocio::hoy())->toDateString();
    }

    /**
     * @param  Builder<Lote>  $query
     * @return Builder<Lote>
     */
    public function scopeVigentes(Builder $query): Builder
    {
        return $query->whereDate('fecha_vencimiento', '>=', FechaNegocio::hoy()->toDateString());
    }
}
