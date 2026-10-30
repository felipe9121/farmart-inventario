<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Violación de una regla de negocio: se responde 422 con un código estable que el
 * frontend puede interpretar. El detalle solo lleva IDs y cantidades, nunca datos
 * personales (RN-10), y no se registra en los logs porque no es un fallo del sistema.
 */
class ReglaNegocioException extends RuntimeException
{
    /**
     * @param  array<string, scalar|null>  $detalle
     */
    public function __construct(
        public readonly string $codigo,
        string $mensaje,
        public readonly array $detalle = [],
        public readonly int $status = 422,
    ) {
        parent::__construct($mensaje);
    }

    public function report(): void
    {
        // Error esperado del dominio: no se envía al log.
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'codigo' => $this->codigo,
            'detalle' => (object) $this->detalle,
        ], $this->status);
    }
}
