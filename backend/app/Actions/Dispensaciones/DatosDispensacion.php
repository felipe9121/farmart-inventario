<?php

declare(strict_types=1);

namespace App\Actions\Dispensaciones;

/**
 * Solicitud de dispensación ya validada en su forma (tipos y rangos).
 */
final readonly class DatosDispensacion
{
    /**
     * @param  list<array{prescripcion_item_id: int, cantidad: int}>  $items
     */
    public function __construct(
        public int $pacienteId,
        public int $prescripcionId,
        public int $bodegaId,
        public array $items,
        public ?CredencialesAutorizacion $autorizacion = null,
    ) {}

    /**
     * RN-09: huella del contenido para detectar una llave de idempotencia reutilizada con
     * otro cuerpo. Es independiente del orden de los ítems y excluye la contraseña.
     */
    public function huella(): string
    {
        $items = array_map(fn (array $item) => [$item['prescripcion_item_id'], $item['cantidad']], $this->items);
        sort($items);

        return hash('sha256', json_encode([
            'paciente_id' => $this->pacienteId,
            'prescripcion_id' => $this->prescripcionId,
            'bodega_id' => $this->bodegaId,
            'items' => $items,
            'autorizador' => $this->autorizacion !== null ? mb_strtolower($this->autorizacion->email) : null,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @return list<int>
     */
    public function idsItems(): array
    {
        return array_map(fn (array $item) => $item['prescripcion_item_id'], $this->items);
    }
}
