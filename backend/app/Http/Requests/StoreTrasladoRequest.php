<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\Traslados\DatosTraslado;

class StoreTrasladoRequest extends TrasladoRequest
{
    protected function habilidad(): string
    {
        return 'create';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'bodega_origen_id' => ['required', 'integer', 'min:1'],
            'bodega_destino_id' => ['required', 'integer', 'min:1', 'different:bodega_origen_id'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.lote_id' => ['required', 'integer', 'min:1', 'distinct'],
            'items.*.cantidad' => ['required', 'integer', 'min:1', 'max:100000'],
            'observaciones' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function datos(): DatosTraslado
    {
        /** @var array<int, array{lote_id: int|string, cantidad: int|string}> $items */
        $items = $this->validated('items');

        return new DatosTraslado(
            $this->integer('bodega_origen_id'),
            $this->integer('bodega_destino_id'),
            array_values(array_map(fn (array $item) => ['lote_id' => (int) $item['lote_id'], 'cantidad' => (int) $item['cantidad']], $items)),
            $this->filled('observaciones') ? $this->string('observaciones')->toString() : null,
        );
    }
}
