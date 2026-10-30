<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DispensacionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $prescripcion_id
 * @property int $bodega_id
 * @property int $dispensado_por
 * @property int|null $autorizado_por
 * @property Carbon|null $autorizado_at
 * @property string|null $idempotency_key
 * @property string|null $idempotency_hash
 */
#[Table('dispensaciones')]
#[Fillable(['prescripcion_id', 'bodega_id', 'dispensado_por', 'autorizado_por', 'autorizado_at', 'idempotency_key', 'idempotency_hash'])]
class Dispensacion extends Model
{
    /** @use HasFactory<DispensacionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            // pdo_sqlsrv devuelve bigint como string.
            'prescripcion_id' => 'integer',
            'bodega_id' => 'integer',
            'dispensado_por' => 'integer',
            'autorizado_por' => 'integer',
            'autorizado_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Prescripcion, $this> */
    public function prescripcion(): BelongsTo
    {
        return $this->belongsTo(Prescripcion::class);
    }

    /** @return BelongsTo<Bodega, $this> */
    public function bodega(): BelongsTo
    {
        return $this->belongsTo(Bodega::class);
    }

    /** @return BelongsTo<User, $this> */
    public function dispensador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispensado_por');
    }

    /** @return BelongsTo<User, $this> */
    public function autorizador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autorizado_por');
    }

    /** @return HasMany<DispensacionItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(DispensacionItem::class);
    }
}
