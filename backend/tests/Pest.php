<?php

declare(strict_types=1);

use App\Exceptions\ReglaNegocioException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\AssertionFailedError;
use Tests\TestCase;

/*
| Las pruebas Feature corren contra SQL Server (base farmart_test, ver phpunit.xml).
| RefreshDatabase envuelve cada prueba en una transacción que se revierte al final.
*/
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Sin RefreshDatabase: los procesos paralelos deben ver datos confirmados.
pest()->extend(TestCase::class)
    ->in('Concurrencia');

/**
 * Ejecuta el callback y devuelve la ReglaNegocioException que lanza, para inspeccionar código y detalle.
 */
function capturarError(Closure $callback): ReglaNegocioException
{
    try {
        $callback();
    } catch (ReglaNegocioException $e) {
        return $e;
    }

    throw new AssertionFailedError('Se esperaba una ReglaNegocioException.');
}
