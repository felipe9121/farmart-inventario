<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

it('usa SQL Server con la base de pruebas', function () {
    expect(DB::connection()->getDriverName())->toBe('sqlsrv')
        ->and(DB::connection()->getDatabaseName())->toBe('farmart_test')
        // 16 = SQL Server 2022.
        ->and((int) DB::scalar("SELECT SERVERPROPERTY('ProductMajorVersion')"))->toBe(16)
        // int llega como int (PDO::SQLSRV_ATTR_FETCHES_NUMERIC_TYPE); bigint sigue como string y se castea en los modelos.
        ->and(DB::scalar('SELECT CAST(42 AS int)'))->toBe(42);
});
