<?php

declare(strict_types=1);

namespace App\Services\Asistente;

/**
 * Lo que el modelo entendió de la pregunta. No contiene datos: solo la intención y sus
 * parámetros; los datos los obtiene el asistente de la BD.
 */
final readonly class Interpretacion
{
    public function __construct(
        public Intencion $intencion,
        public ?int $bodegaId = null,
        public ?int $dias = null,
    ) {}
}
