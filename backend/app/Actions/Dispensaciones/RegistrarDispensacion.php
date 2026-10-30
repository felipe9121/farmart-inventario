<?php

declare(strict_types=1);

namespace App\Actions\Dispensaciones;

use App\Enums\Rol;
use App\Enums\TipoMovimiento;
use App\Exceptions\ReglaNegocioException;
use App\Models\Bodega;
use App\Models\Dispensacion;
use App\Models\DispensacionItem;
use App\Models\Prescripcion;
use App\Models\PrescripcionItem;
use App\Models\User;
use App\Services\Inventario\InventarioService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Registra una dispensación (RN-02, RN-04, RN-05, RN-09).
 *
 * Todo ocurre en una transacción que se reintenta si SQL Server la elige como víctima
 * de un deadlock. Orden de bloqueo: prescripción -> ítems de la prescripción (por id)
 * -> existencias (por producto_id y luego id). Así dos dispensaciones concurrentes de
 * la misma prescripción se serializan y nunca se bloquean en orden cruzado.
 */
final class RegistrarDispensacion
{
    public function __construct(private readonly InventarioService $inventario) {}

    public function ejecutar(User $dispensador, DatosDispensacion $datos, string $llave): ResultadoDispensacion
    {
        $huella = $datos->huella();

        $previa = $this->buscarPrevia($dispensador, $llave, $huella);
        if ($previa !== null) {
            return new ResultadoDispensacion($previa, repetida: true);
        }

        // La verificación de la clave (bcrypt, lenta) va fuera de la transacción para no
        // retener bloqueos de stock mientras se calcula.
        $autorizador = $this->requiereAutorizacion($datos)
            ? $this->verificarAutorizador($dispensador, $datos->autorizacion)
            : null;

        try {
            $dispensacion = DB::transaction(
                fn () => $this->registrar($dispensador, $autorizador, $datos, $llave, $huella),
                config()->integer('farmart.intentos_transaccion'),
            );
        } catch (UniqueConstraintViolationException $e) {
            // Una petición concurrente con la misma llave se confirmó primero: se devuelve la suya.
            $previa = str_contains($e->getMessage(), 'uq_dispensaciones_idempotencia')
                ? $this->buscarPrevia($dispensador, $llave, $huella)
                : null;

            if ($previa === null) {
                throw $e;
            }

            return new ResultadoDispensacion($previa, repetida: true);
        }

        return new ResultadoDispensacion($dispensacion, repetida: false);
    }

    private function registrar(User $dispensador, ?User $autorizador, DatosDispensacion $datos, string $llave, string $huella): Dispensacion
    {
        $prescripcion = Prescripcion::query()->lockForUpdate()->find($datos->prescripcionId)
            ?? throw new ReglaNegocioException('PRESCRIPCION_NO_ENCONTRADA', 'La prescripción no existe.');

        if ($prescripcion->paciente_id !== $datos->pacienteId) {
            throw new ReglaNegocioException('PRESCRIPCION_DE_OTRO_PACIENTE', 'La prescripción no pertenece al paciente indicado.');
        }

        if (! $prescripcion->estaVigente()) {
            throw new ReglaNegocioException('PRESCRIPCION_NO_VIGENTE', 'La prescripción no está vigente.', [
                'vigente_hasta' => $prescripcion->vigente_hasta->toDateString(),
            ]);
        }

        $bodega = Bodega::query()->find($datos->bodegaId);
        if ($bodega === null || ! $bodega->activa) {
            throw new ReglaNegocioException('BODEGA_NO_DISPONIBLE', 'La bodega no existe o está inactiva.');
        }

        $items = $this->bloquearItems($prescripcion, $datos);

        if ($autorizador === null && $items->contains(fn (PrescripcionItem $item) => $item->producto->es_control_especial)) {
            // Se revisa de nuevo con los datos bloqueados por si el producto cambió entre tanto.
            throw new ReglaNegocioException('AUTORIZACION_REQUERIDA', 'Hay productos de control especial: se requiere la autorización de un regente.');
        }

        $dispensacion = Dispensacion::query()->create([
            'prescripcion_id' => $prescripcion->id,
            'bodega_id' => $bodega->id,
            'dispensado_por' => $dispensador->id,
            'autorizado_por' => $autorizador?->id,
            'autorizado_at' => $autorizador !== null ? now() : null,
            'idempotency_key' => $llave,
            'idempotency_hash' => $huella,
        ]);

        $solicitudes = collect($datos->items)
            ->sortBy(fn (array $s) => [$items[$s['prescripcion_item_id']]->producto_id, $s['prescripcion_item_id']]);

        foreach ($solicitudes as $solicitud) {
            $item = $items[$solicitud['prescripcion_item_id']];

            foreach ($this->inventario->asignarFefo($bodega->id, $item->producto_id, $solicitud['cantidad']) as $asignacion) {
                $detalle = DispensacionItem::query()->create([
                    'dispensacion_id' => $dispensacion->id,
                    'prescripcion_item_id' => $item->id,
                    'lote_id' => $asignacion->existencia->lote_id,
                    'cantidad' => $asignacion->cantidad,
                ]);

                $this->inventario->registrarSalida(
                    $asignacion->existencia, $asignacion->cantidad, TipoMovimiento::Dispensacion, $dispensador,
                    ['dispensacion_item_id' => $detalle->id],
                );
            }

            // RN-04: acumulado de parciales; el CHECK de la BD respalda el tope.
            $item->cantidad_dispensada += $solicitud['cantidad'];
            $item->save();
        }

        return $dispensacion;
    }

    /**
     * @return Collection<int, PrescripcionItem> indexada por id
     */
    private function bloquearItems(Prescripcion $prescripcion, DatosDispensacion $datos): Collection
    {
        $items = PrescripcionItem::query()
            ->with('producto')
            ->where('prescripcion_id', $prescripcion->id)
            ->whereIn('id', $datos->idsItems())
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($datos->items as $solicitud) {
            $item = $items->get($solicitud['prescripcion_item_id'])
                ?? throw new ReglaNegocioException('ITEM_NO_PERTENECE_A_PRESCRIPCION', 'Un ítem no pertenece a la prescripción.', [
                    'prescripcion_item_id' => $solicitud['prescripcion_item_id'],
                ]);

            if (! $item->producto->activo) {
                throw new ReglaNegocioException('PRODUCTO_INACTIVO', 'El producto está inactivo.', ['producto_id' => $item->producto_id]);
            }

            if ($solicitud['cantidad'] > $item->cantidadPendiente()) {
                throw new ReglaNegocioException('EXCEDE_LO_PRESCRITO', 'La cantidad supera lo pendiente por dispensar.', [
                    'prescripcion_item_id' => $item->id,
                    'solicitado' => $solicitud['cantidad'],
                    'pendiente' => $item->cantidadPendiente(),
                ]);
            }
        }

        return $items;
    }

    private function requiereAutorizacion(DatosDispensacion $datos): bool
    {
        return PrescripcionItem::query()
            ->where('prescripcion_id', $datos->prescripcionId)
            ->whereIn('id', $datos->idsItems())
            ->whereHas('producto', fn ($q) => $q->where('es_control_especial', true))
            ->exists();
    }

    /**
     * RN-05: un segundo usuario, regente activo, distinto de quien dispensa.
     */
    private function verificarAutorizador(User $dispensador, ?CredencialesAutorizacion $credenciales): User
    {
        if ($credenciales === null) {
            throw new ReglaNegocioException('AUTORIZACION_REQUERIDA', 'Hay productos de control especial: se requiere la autorización de un regente.');
        }

        $autorizador = User::query()->where('email', $credenciales->email)->first();

        // Mismo mensaje para usuario inexistente o clave errónea: no revela qué cuentas existen.
        if ($autorizador === null || ! $autorizador->activo || ! Hash::check($credenciales->password, $autorizador->password)) {
            throw new ReglaNegocioException('AUTORIZACION_INVALIDA', 'Las credenciales de autorización no son válidas.');
        }

        if ($autorizador->id === $dispensador->id) {
            throw new ReglaNegocioException('AUTORIZADOR_ES_DISPENSADOR', 'Quien dispensa no puede autorizar su propia dispensación.');
        }

        if (! $autorizador->tieneRol(Rol::RegenteFarmacia)) {
            throw new ReglaNegocioException('AUTORIZADOR_NO_REGENTE', 'La autorización debe darla un regente de farmacia.');
        }

        return $autorizador;
    }

    private function buscarPrevia(User $dispensador, string $llave, string $huella): ?Dispensacion
    {
        $previa = Dispensacion::query()
            ->where('dispensado_por', $dispensador->id)
            ->where('idempotency_key', $llave)
            ->first();

        if ($previa !== null && ! hash_equals((string) $previa->idempotency_hash, $huella)) {
            throw new ReglaNegocioException('IDEMPOTENCY_KEY_REUTILIZADA', 'La llave de idempotencia ya se usó con un contenido distinto.');
        }

        return $previa;
    }
}
