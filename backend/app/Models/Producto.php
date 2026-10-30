<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProductoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property string $presentacion
 * @property string $unidad_medida
 * @property bool $es_control_especial
 * @property bool $activo
 */
#[Table('productos')]
#[Fillable(['codigo', 'nombre', 'presentacion', 'unidad_medida', 'es_control_especial', 'activo'])]
class Producto extends Model
{
    /** @use HasFactory<ProductoFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'es_control_especial' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    /** @return HasMany<Lote, $this> */
    public function lotes(): HasMany
    {
        return $this->hasMany(Lote::class);
    }
}
