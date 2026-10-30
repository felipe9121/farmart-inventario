<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoMovimiento;
use Database\Factories\MovimientoKardexFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Movimiento de kardex inmutable (RN-06). La BD lo garantiza con triggers
 * INSTEAD OF UPDATE/DELETE; el modelo falla antes para dar un error claro.
 *
 * @property int $id
 * @property int $bodega_id
 * @property int $producto_id
 * @property int $lote_id
 * @property TipoMovimiento $tipo
 * @property int $cantidad
 * @property int $saldo_resultante
 * @property string|null $motivo
 * @property string|null $documento_referencia
 * @property int|null $dispensacion_item_id
 * @property int|null $traslado_item_id
 * @property int $usuario_id
 * @property Carbon $created_at
 */
#[Table('movimientos_kardex', timestamps: false)]
#[Fillable([
    'bodega_id', 'producto_id', 'lote_id', 'tipo', 'cantidad', 'saldo_resultante', 'motivo',
    'documento_referencia', 'dispensacion_item_id', 'traslado_item_id', 'usuario_id',
])]
class MovimientoKardex extends Model
{
    /** @use HasFactory<MovimientoKardexFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Los movimientos de kardex son inmutables; registre un ajuste.'));
        static::deleting(fn () => throw new LogicException('Los movimientos de kardex son inmutables; registre un ajuste.'));
    }

    protected function casts(): array
    {
        return [
            'tipo' => TipoMovimiento::class,
            'cantidad' => 'integer',
            'saldo_resultante' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Bodega, $this> */
    public function bodega(): BelongsTo
    {
        return $this->belongsTo(Bodega::class);
    }

    /** @return BelongsTo<Lote, $this> */
    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class);
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
