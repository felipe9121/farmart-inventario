<?php

declare(strict_types=1);

namespace App\Http\Requests;

/**
 * Transiciones sin cuerpo (solicitar, aprobar): la habilidad es el nombre de la ruta.
 */
class TransicionTrasladoRequest extends TrasladoRequest
{
    protected function habilidad(): string
    {
        $accion = $this->route()?->getActionMethod();
        assert(in_array($accion, ['solicitar', 'aprobar'], true));

        return $accion;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
