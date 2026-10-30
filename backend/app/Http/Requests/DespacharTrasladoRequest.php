<?php

declare(strict_types=1);

namespace App\Http\Requests;

class DespacharTrasladoRequest extends TrasladoRequest
{
    protected function habilidad(): string
    {
        return 'despachar';
    }

    /**
     * Los ítems son opcionales: los omitidos se despachan completos.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'items' => ['sometimes', 'array', 'max:50'],
            'items.*.traslado_item_id' => ['required', 'integer', 'min:1', 'distinct'],
            'items.*.cantidad' => ['required', 'integer', 'min:0', 'max:100000'],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function cantidades(): array
    {
        return $this->cantidadesPorItem('cantidad');
    }
}
