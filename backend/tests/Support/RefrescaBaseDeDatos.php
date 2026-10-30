<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * RefreshDatabase que migra con la conexión de administración. Las pruebas usan la
 * conexión de la app (usuario sin DDL): si pasan, la app funciona con permisos mínimos.
 */
trait RefrescaBaseDeDatos
{
    use RefreshDatabase {
        migrateFreshUsing as migrateFreshUsingBase;
    }

    /**
     * @return array<string, mixed>
     */
    protected function migrateFreshUsing()
    {
        return [...$this->migrateFreshUsingBase(), '--database' => 'sqlsrv_admin'];
    }
}
