<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoTraslado;
use Database\Factories\TrasladoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $codigo
 * @property int $bodega_origen_id
 * @property int $bodega_destino_id
 * @property EstadoTraslado $estado
 * @property int $creado_por
 * @property int|null $solicitado_por
 * @property Carbon|null $solicitado_at
 * @property int|null $aprobado_por
 * @property Carbon|null $aprobado_at
 * @property int|null $despachado_por
 * @property Carbon|null $despachado_at
 * @property int|null $recibido_por
 * @property Carbon|null $recibido_at
 * @property int|null $anulado_por
 * @property Carbon|null $anulado_at
 * @property string|null $motivo_anulacion
 * @property string|null $observaciones
 */
#[Table('traslados')]
#[Fillable(['codigo', 'bodega_origen_id', 'bodega_destino_id', 'creado_por', 'observaciones'])]
class Traslado extends Model
{
    /** @use HasFactory<TrasladoFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = ['estado' => 'BORRADOR'];

    protected function casts(): array
    {
        return [
            'estado' => EstadoTraslado::class,
            'solicitado_at' => 'datetime',
            'aprobado_at' => 'datetime',
            'despachado_at' => 'datetime',
            'recibido_at' => 'datetime',
            'anulado_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Bodega, $this> */
    public function bodegaOrigen(): BelongsTo
    {
        return $this->belongsTo(Bodega::class, 'bodega_origen_id');
    }

    /** @return BelongsTo<Bodega, $this> */
    public function bodegaDestino(): BelongsTo
    {
        return $this->belongsTo(Bodega::class, 'bodega_destino_id');
    }

    /** @return BelongsTo<User, $this> */
    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitado_por');
    }

    /** @return BelongsTo<User, $this> */
    public function aprobador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }

    /** @return HasMany<TrasladoItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(TrasladoItem::class);
    }
}
