<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ExistenciaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stock de un lote en una bodega. Solo debe modificarse desde los servicios de
 * inventario, dentro de una transacción y generando su movimiento de kardex.
 *
 * @property int $id
 * @property int $bodega_id
 * @property int $producto_id
 * @property int $lote_id
 * @property int $cantidad
 */
#[Table('existencias')]
#[Fillable(['bodega_id', 'producto_id', 'lote_id', 'cantidad'])]
class Existencia extends Model
{
    /** @use HasFactory<ExistenciaFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            // pdo_sqlsrv devuelve bigint como string.
            'bodega_id' => 'integer',
            'producto_id' => 'integer',
            'lote_id' => 'integer',
            'cantidad' => 'integer',
        ];
    }

    /** @return BelongsTo<Bodega, $this> */
    public function bodega(): BelongsTo
    {
        return $this->belongsTo(Bodega::class);
    }

    /** @return BelongsTo<Producto, $this> */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    /** @return BelongsTo<Lote, $this> */
    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class);
    }
}
