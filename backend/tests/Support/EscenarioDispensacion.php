<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Actions\Dispensaciones\CredencialesAutorizacion;
use App\Actions\Dispensaciones\DatosDispensacion;
use App\Enums\Rol;
use App\Models\Bodega;
use App\Models\Existencia;
use App\Models\Lote;
use App\Models\Paciente;
use App\Models\Prescripcion;
use App\Models\PrescripcionItem;
use App\Models\Producto;
use App\Models\User;

/**
 * Bodega, paciente con prescripción vigente, un auxiliar y un regente listos para dispensar.
 */
final class EscenarioDispensacion
{
    public function __construct(
        public readonly User $auxiliar,
        public readonly User $regente,
        public readonly Bodega $bodega,
        public readonly Paciente $paciente,
        public readonly Prescripcion $prescripcion,
    ) {}

    public static function crear(): self
    {
        $paciente = Paciente::factory()->create();

        return new self(
            User::factory()->rol(Rol::AuxiliarFarmacia)->create(),
            User::factory()->rol(Rol::RegenteFarmacia)->create(['email' => 'regente@farmart.test']),
            Bodega::factory()->create(),
            $paciente,
            Prescripcion::factory()->create(['paciente_id' => $paciente->id]),
        );
    }

    public function item(int $prescrita = 10, bool $controlEspecial = false): PrescripcionItem
    {
        $producto = Producto::factory()->create(['es_control_especial' => $controlEspecial]);

        return PrescripcionItem::factory()->create([
            'prescripcion_id' => $this->prescripcion->id,
            'producto_id' => $producto->id,
            'cantidad_prescrita' => $prescrita,
        ]);
    }

    public function stock(PrescripcionItem $item, int $cantidad, int $diasParaVencer = 180, ?Bodega $bodega = null): Existencia
    {
        $lote = Lote::factory()->venceEnDias($diasParaVencer)->create(['producto_id' => $item->producto_id]);

        return Existencia::factory()->create([
            'bodega_id' => ($bodega ?? $this->bodega)->id,
            'lote_id' => $lote->id,
            'producto_id' => $item->producto_id,
            'cantidad' => $cantidad,
        ]);
    }

    /**
     * @param  list<array{PrescripcionItem, int}>  $items  pares [ítem, cantidad]
     */
    public function datos(array $items, ?CredencialesAutorizacion $autorizacion = null): DatosDispensacion
    {
        return new DatosDispensacion(
            $this->paciente->id,
            $this->prescripcion->id,
            $this->bodega->id,
            array_map(fn (array $par) => ['prescripcion_item_id' => $par[0]->id, 'cantidad' => $par[1]], $items),
            $autorizacion,
        );
    }

    public function firmaRegente(): CredencialesAutorizacion
    {
        return new CredencialesAutorizacion($this->regente->email, 'password');
    }
}
