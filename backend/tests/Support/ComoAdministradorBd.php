<?php

declare(strict_types=1);

namespace Tests\Support;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

/**
 * Ejecuta SQL con la conexión de administración (sin los DENY del usuario de la app) para
 * probar la segunda barrera: los triggers. Todo ocurre en una transacción propia que
 * siempre se revierte, así que no deja datos.
 */
final class ComoAdministradorBd
{
    /**
     * @template T
     *
     * @param  Closure(Connection): T  $callback
     * @return T
     */
    public static function enTransaccion(Closure $callback): mixed
    {
        $db = DB::connection('sqlsrv_admin');
        $db->beginTransaction();

        try {
            return $callback($db);
        } finally {
            // Si un trigger lanzó THROW, el servidor ya revirtió; el driver abrió una
            // transacción implícita nueva que también se revierte aquí.
            $db->rollBack();
        }
    }

    /**
     * Inserta lo mínimo para un movimiento de kardex y devuelve su id.
     */
    public static function insertarMovimiento(Connection $db): int
    {
        $usuario = self::insertarUsuario($db);
        $bodega = $db->table('bodegas')->insertGetId(['codigo' => 'ADM-1', 'nombre' => 'Bodega admin']);
        $producto = $db->table('productos')->insertGetId([
            'codigo' => 'ADM-1', 'nombre' => 'Producto admin', 'presentacion' => 'Tableta', 'unidad_medida' => 'unidad',
        ]);
        $lote = $db->table('lotes')->insertGetId(['producto_id' => $producto, 'numero_lote' => 'ADM-1', 'fecha_vencimiento' => '2030-01-01']);

        return (int) $db->table('movimientos_kardex')->insertGetId([
            'bodega_id' => $bodega, 'producto_id' => $producto, 'lote_id' => $lote,
            'tipo' => 'ENTRADA', 'cantidad' => 10, 'saldo_resultante' => 10, 'usuario_id' => $usuario,
        ]);
    }

    public static function insertarRegistroBitacora(Connection $db): int
    {
        return (int) $db->table('bitacora_accesos_paciente')->insertGetId([
            'usuario_id' => self::insertarUsuario($db), 'rol' => 'auditor', 'accion' => 'BUSQUEDA',
        ]);
    }

    private static function insertarUsuario(Connection $db): int
    {
        return (int) $db->table('users')->insertGetId([
            'name' => 'Admin BD', 'email' => 'admin-bd@farmart.test', 'password' => 'x', 'rol' => 'admin',
        ]);
    }
}
