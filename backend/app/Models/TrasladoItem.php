<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TrasladoItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $traslado_id
 * @property int $lote_id
 * @property int $cantidad_solicitada
 * @property int|null $cantidad_despachada
 * @property int|null $cantidad_recibida
 * @property-read int|null $cantidad_discrepancia Columna calculada en la BD.
 */
#[Table('traslado_items')]
#[Fillable(['traslado_id', 'lote_id', 'cantidad_solicitada'])]
class TrasladoItem extends Model
{
    /** @use HasFactory<TrasladoItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'cantidad_solicitada' => 'integer',
            'cantidad_despachada' => 'integer',
            'cantidad_recibida' => 'integer',
            'cantidad_discrepancia' => 'integer',
        ];
    }

    /** @return BelongsTo<Traslado, $this> */
    public function traslado(): BelongsTo
    {
        return $this->belongsTo(Traslado::class);
    }

    /** @return BelongsTo<Lote, $this> */
    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class);
    }
}
