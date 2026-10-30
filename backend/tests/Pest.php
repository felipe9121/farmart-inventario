<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
| Las pruebas Feature corren contra SQL Server (base farmart_test, ver phpunit.xml).
| RefreshDatabase envuelve cada prueba en una transacción que se revierte al final.
*/
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');
