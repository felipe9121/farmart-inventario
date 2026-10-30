<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * "Hoy" según la zona horaria del negocio. Las reglas con fecha comparan fechas
 * (Y-m-d), no instantes, para no mezclar zonas horarias.
 */
final class FechaNegocio
{
    public static function hoy(): CarbonImmutable
    {
        return CarbonImmutable::now(config()->string('farmart.zona_horaria'))->startOfDay();
    }
}
