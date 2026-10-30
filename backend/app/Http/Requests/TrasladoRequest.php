<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Traslado;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base de las peticiones sobre un traslado: autoriza con la habilidad de la policy.
 * El controlador recibe Traslado tipado para que el binding implícito resuelva {traslado}.
 */
abstract class TrasladoRequest extends FormRequest
{
    abstract protected function habilidad(): string;

    public function authorize(): bool
    {
        $traslado = $this->route('traslado');

        return $this->usuario()->can($this->habilidad(), $traslado instanceof Traslado ? $traslado : Traslado::class);
    }

    public function usuario(): User
    {
        $usuario = $this->user();
        assert($usuario instanceof User);

        return $usuario;
    }

    /**
     * @return array<int, int> traslado_item_id => cantidad
     */
    protected function cantidadesPorItem(string $campo): array
    {
        /** @var array<int, array<string, int|string>> $items */
        $items = $this->validated('items', []);

        $cantidades = [];
        foreach ($items as $item) {
            $cantidades[(int) $item['traslado_item_id']] = (int) $item[$campo];
        }

        return $cantidades;
    }
}
