<?php

declare(strict_types=1);

namespace App\Http\Requests;

class RecibirTrasladoRequest extends TrasladoRequest
{
    protected function habilidad(): string
    {
        return 'recibir';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.traslado_item_id' => ['required', 'integer', 'min:1', 'distinct'],
            'items.*.cantidad_recibida' => ['required', 'integer', 'min:0', 'max:100000'],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function recibidas(): array
    {
        return $this->cantidadesPorItem('cantidad_recibida');
    }
}
