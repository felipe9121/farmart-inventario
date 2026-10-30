<?php

declare(strict_types=1);

namespace App\Http\Requests;

class AnularTrasladoRequest extends TrasladoRequest
{
    protected function habilidad(): string
    {
        return 'anular';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }
}
