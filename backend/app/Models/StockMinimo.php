<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\StockMinimoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $bodega_id
 * @property int $producto_id
 * @property int $cantidad_minima
 */
#[Table('stock_minimos')]
#[Fillable(['bodega_id', 'producto_id', 'cantidad_minima'])]
class StockMinimo extends Model
{
    /** @use HasFactory<StockMinimoFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['cantidad_minima' => 'integer'];
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
}
