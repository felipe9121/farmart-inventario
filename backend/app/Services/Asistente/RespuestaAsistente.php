<?php

declare(strict_types=1);

namespace App\Services\Asistente;

final readonly class RespuestaAsistente
{
    /**
     * @param  list<array<string, scalar>>  $datos  filas de la consulta que respaldan la respuesta
     * @param  list<string>  $sugerencias
     */
    public function __construct(
        public Intencion $intencion,
        public string $texto,
        public array $datos,
        public Interpretacion $interpretacion,
        public string $modelo,
        public array $sugerencias = [],
    ) {}
}
