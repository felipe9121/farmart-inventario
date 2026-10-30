<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Actions\Traslados\AprobarTraslado;
use App\Actions\Traslados\CrearTraslado;
use App\Actions\Traslados\DatosTraslado;
use App\Actions\Traslados\SolicitarTraslado;
use App\Enums\Rol;
use App\Models\Bodega;
use App\Models\Existencia;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\Traslado;
use App\Models\User;

/**
 * Dos bodegas, un auxiliar que solicita y un regente que aprueba.
 */
final class EscenarioTraslado
{
    public function __construct(
        public readonly User $auxiliar,
        public readonly User $regente,
        public readonly Bodega $origen,
        public readonly Bodega $destino,
    ) {}

    public static function crear(): self
    {
        return new self(
            User::factory()->rol(Rol::AuxiliarFarmacia)->create(),
            User::factory()->rol(Rol::RegenteFarmacia)->create(),
            Bodega::factory()->create(),
            Bodega::factory()->create(),
        );
    }

    public function stockEnOrigen(int $cantidad, int $diasParaVencer = 180, ?Producto $producto = null): Existencia
    {
        $lote = Lote::factory()->venceEnDias($diasParaVencer)->create(['producto_id' => ($producto ?? Producto::factory()->create())->id]);

        return Existencia::factory()->create([
            'bodega_id' => $this->origen->id, 'lote_id' => $lote->id, 'producto_id' => $lote->producto_id, 'cantidad' => $cantidad,
        ]);
    }

    /**
     * @param  list<array{Existencia, int}>  $items  pares [existencia en origen, cantidad]
     */
    public function datos(array $items): DatosTraslado
    {
        return new DatosTraslado(
            $this->origen->id,
            $this->destino->id,
            array_map(fn (array $par) => ['lote_id' => $par[0]->lote_id, 'cantidad' => $par[1]], $items),
        );
    }

    /**
     * @param  list<array{Existencia, int}>  $items
     */
    public function borrador(array $items): Traslado
    {
        return app(CrearTraslado::class)->ejecutar($this->auxiliar, $this->datos($items));
    }

    /**
     * @param  list<array{Existencia, int}>  $items
     */
    public function aprobado(array $items): Traslado
    {
        $traslado = app(SolicitarTraslado::class)->ejecutar($this->auxiliar, $this->borrador($items));

        return app(AprobarTraslado::class)->ejecutar($this->regente, $traslado);
    }
}
