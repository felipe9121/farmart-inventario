<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\MovimientoKardex;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreAjusteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->usuario()->can('ajustar', MovimientoKardex::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'bodega_id' => ['required', 'integer', 'min:1'],
            'lote_id' => ['required', 'integer', 'min:1'],
            'cantidad' => ['required', 'integer', 'not_in:0', 'between:-100000,100000'],
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    public function usuario(): User
    {
        $usuario = $this->user();
        assert($usuario instanceof User);

        return $usuario;
    }
}
