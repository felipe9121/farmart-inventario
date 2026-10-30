<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\BodegaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property bool $activa
 */
#[Table('bodegas')]
#[Fillable(['codigo', 'nombre', 'activa'])]
class Bodega extends Model
{
    /** @use HasFactory<BodegaFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['activa' => 'boolean'];
    }
}
