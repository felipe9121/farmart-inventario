<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

it('usa SQL Server con la base de pruebas', function () {
    expect(DB::connection()->getDriverName())->toBe('sqlsrv')
        ->and(DB::connection()->getDatabaseName())->toBe('farmart_test')
        // pdo_sqlsrv devuelve los escalares como string. 16 = SQL Server 2022.
        ->and((int) DB::scalar("SELECT SERVERPROPERTY('ProductMajorVersion')"))->toBe(16);
});
