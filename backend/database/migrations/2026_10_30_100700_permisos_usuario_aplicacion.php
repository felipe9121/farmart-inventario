<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Mínimo privilegio para el usuario de la app (creado por docker/sqlserver). Defensa en
 * profundidad: aunque alguien se saltara la app, el usuario no puede editar ni borrar la
 * historia. El usuario no tiene DDL, así que tampoco puede desactivar los triggers.
 */
return new class extends Migration
{
    /** Historia inmutable: ni UPDATE ni DELETE (RN-06, RN-10). */
    private const INMUTABLES = ['movimientos_kardex', 'bitacora_accesos_paciente'];

    /** Documentos y catálogos con historia: se anulan o desactivan, nunca se borran. */
    private const SIN_BORRADO = [
        'bodegas', 'productos', 'lotes', 'existencias', 'stock_minimos', 'pacientes', 'prescripciones',
        'prescripcion_items', 'dispensaciones', 'dispensacion_items', 'traslados', 'traslado_items', 'users',
    ];

    public function up(): void
    {
        $usuario = $this->usuario();
        if ($usuario === null) {
            return;
        }

        foreach (self::INMUTABLES as $tabla) {
            DB::statement("DENY UPDATE, DELETE ON dbo.{$tabla} TO {$usuario}");
        }
        foreach (self::SIN_BORRADO as $tabla) {
            DB::statement("DENY DELETE ON dbo.{$tabla} TO {$usuario}");
        }
    }

    public function down(): void
    {
        $usuario = $this->usuario();
        if ($usuario === null) {
            return;
        }

        foreach ([...self::INMUTABLES, ...self::SIN_BORRADO] as $tabla) {
            DB::statement("REVOKE UPDATE, DELETE ON dbo.{$tabla} TO {$usuario}");
        }
    }

    /**
     * Nombre entre corchetes del usuario de la app, o null si no existe en esta base
     * (p. ej. si la app corre como sa: entonces no hay a quién restringir).
     */
    private function usuario(): ?string
    {
        $nombre = config()->string('farmart.usuario_bd_aplicacion');

        if (DB::scalar('SELECT DATABASE_PRINCIPAL_ID(?)', [$nombre]) === null) {
            return null;
        }

        return '['.str_replace(']', ']]', $nombre).']';
    }
};
