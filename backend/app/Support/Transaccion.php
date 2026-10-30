<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Transacción de stock: si SQL Server la elige como víctima de un deadlock (1205),
 * DB::transaction la reintenta hasta farmart.intentos_transaccion veces.
 */
final class Transaccion
{
    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function conReintento(Closure $callback): mixed
    {
        return DB::transaction($callback, config()->integer('farmart.intentos_transaccion'));
    }
}
