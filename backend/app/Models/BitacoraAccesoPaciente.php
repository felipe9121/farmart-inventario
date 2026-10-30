<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AccionBitacora;
use App\Enums\Rol;
use Database\Factories\BitacoraAccesoPacienteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property int $usuario_id
 * @property Rol $rol
 * @property int|null $paciente_id
 * @property AccionBitacora $accion
 * @property string|null $ip
 * @property Carbon $created_at
 */
#[Table('bitacora_accesos_paciente', timestamps: false)]
#[Fillable(['usuario_id', 'rol', 'paciente_id', 'accion', 'ip'])]
class BitacoraAccesoPaciente extends Model
{
    /** @use HasFactory<BitacoraAccesoPacienteFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('La bitácora de accesos es inmutable.'));
        static::deleting(fn () => throw new LogicException('La bitácora de accesos es inmutable.'));
    }

    protected function casts(): array
    {
        return [
            'rol' => Rol::class,
            'accion' => AccionBitacora::class,
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /** @return BelongsTo<Paciente, $this> */
    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class);
    }
}
