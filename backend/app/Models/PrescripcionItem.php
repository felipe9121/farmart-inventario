<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PrescripcionItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $prescripcion_id
 * @property int $producto_id
 * @property int $cantidad_prescrita
 * @property int $cantidad_dispensada
 * @property string|null $posologia
 */
#[Table('prescripcion_items')]
#[Fillable(['prescripcion_id', 'producto_id', 'cantidad_prescrita', 'posologia'])]
class PrescripcionItem extends Model
{
    /** @use HasFactory<PrescripcionItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            // pdo_sqlsrv devuelve bigint como string.
            'prescripcion_id' => 'integer',
            'producto_id' => 'integer',
            'cantidad_prescrita' => 'integer',
            'cantidad_dispensada' => 'integer',
        ];
    }

    /** @return BelongsTo<Prescripcion, $this> */
    public function prescripcion(): BelongsTo
    {
        return $this->belongsTo(Prescripcion::class);
    }

    /** @return BelongsTo<Producto, $this> */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function cantidadPendiente(): int
    {
        return $this->cantidad_prescrita - $this->cantidad_dispensada;
    }
}
