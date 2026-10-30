<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\Dispensaciones\CredencialesAutorizacion;
use App\Actions\Dispensaciones\DatosDispensacion;
use App\Http\Middleware\RequiereIdempotencyKey;
use App\Models\Dispensacion;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreDispensacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->usuario()->can('create', Dispensacion::class);
    }

    /**
     * Solo la forma de los datos; las reglas de negocio están en RegistrarDispensacion.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'paciente_id' => ['required', 'integer', 'min:1'],
            'prescripcion_id' => ['required', 'integer', 'min:1'],
            'bodega_id' => ['required', 'integer', 'min:1'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.prescripcion_item_id' => ['required', 'integer', 'min:1', 'distinct'],
            'items.*.cantidad' => ['required', 'integer', 'min:1', 'max:100000'],
            // RN-05: co-firma del regente cuando hay productos de control especial.
            'autorizacion' => ['nullable', 'array:email,password'],
            'autorizacion.email' => ['required_with:autorizacion', 'string', 'email', 'max:255'],
            'autorizacion.password' => ['required_with:autorizacion', 'string', 'max:255'],
        ];
    }

    public function usuario(): User
    {
        $usuario = $this->user();
        assert($usuario instanceof User);

        return $usuario;
    }

    public function llaveIdempotencia(): string
    {
        return (string) $this->header(RequiereIdempotencyKey::HEADER);
    }

    public function datos(): DatosDispensacion
    {
        /** @var array<int, array{prescripcion_item_id: int|string, cantidad: int|string}> $items */
        $items = $this->validated('items');
        /** @var array{email: string, password: string}|null $autorizacion */
        $autorizacion = $this->validated('autorizacion');

        return new DatosDispensacion(
            pacienteId: $this->integer('paciente_id'),
            prescripcionId: $this->integer('prescripcion_id'),
            bodegaId: $this->integer('bodega_id'),
            items: array_map(fn (array $item) => [
                'prescripcion_item_id' => (int) $item['prescripcion_item_id'],
                'cantidad' => (int) $item['cantidad'],
            ], array_values($items)),
            autorizacion: $autorizacion !== null
                ? new CredencialesAutorizacion($autorizacion['email'], $autorizacion['password'])
                : null,
        );
    }
}
