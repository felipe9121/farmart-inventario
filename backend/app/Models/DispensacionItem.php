<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DispensacionItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $dispensacion_id
 * @property int $prescripcion_item_id
 * @property int $lote_id
 * @property int $cantidad
 */
#[Table('dispensacion_items')]
#[Fillable(['dispensacion_id', 'prescripcion_item_id', 'lote_id', 'cantidad'])]
class DispensacionItem extends Model
{
    /** @use HasFactory<DispensacionItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            // pdo_sqlsrv devuelve bigint como string.
            'dispensacion_id' => 'integer',
            'prescripcion_item_id' => 'integer',
            'lote_id' => 'integer',
            'cantidad' => 'integer',
        ];
    }

    /** @return BelongsTo<Dispensacion, $this> */
    public function dispensacion(): BelongsTo
    {
        return $this->belongsTo(Dispensacion::class);
    }

    /** @return BelongsTo<PrescripcionItem, $this> */
    public function prescripcionItem(): BelongsTo
    {
        return $this->belongsTo(PrescripcionItem::class);
    }

    /** @return BelongsTo<Lote, $this> */
    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class);
    }
}
