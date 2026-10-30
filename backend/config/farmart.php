<?php

declare(strict_types=1);

return [
    // Zona horaria de las reglas de negocio con fecha (vencimiento de lotes, vigencia de
    // prescripciones). Los timestamps se guardan en UTC (app.timezone).
    'zona_horaria' => env('FARMART_ZONA_HORARIA', 'America/Bogota'),

    // Intentos de una transacción de stock cuando SQL Server la elige como víctima de un deadlock.
    'intentos_transaccion' => (int) env('FARMART_INTENTOS_TRANSACCION', 3),

    // Usuario de BD de la app, al que las migraciones le niegan UPDATE/DELETE sobre
    // tablas con historia (defensa en profundidad junto con los triggers).
    'usuario_bd_aplicacion' => env('DB_USERNAME', 'farmart_app'),
];
