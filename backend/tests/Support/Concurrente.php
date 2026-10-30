<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Actions\Dispensaciones\DatosDispensacion;
use App\Actions\Dispensaciones\RegistrarDispensacion;
use App\Actions\Traslados\AprobarTraslado;
use App\Actions\Traslados\DespacharTraslado;
use App\Actions\Traslados\RecibirTraslado;
use App\Exceptions\ReglaNegocioException;
use App\Models\Traslado;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Tareas para Concurrency::run. Se crean en métodos estáticos de una clase autocargable:
 * un closure definido dentro de una prueba queda ligado a la clase de Pest, que no existe
 * en el proceso hijo.
 *
 * Cada tarea espera a la barrera de tiempo (todas arrancan a la vez) y devuelve
 * ['ok' => bool, 'id' => ?, 'repetida' => ?, 'codigo' => ?] en lugar de lanzar.
 */
final class Concurrente
{
    public static function baseDeDatos(): Closure
    {
        return fn () => DB::connection()->getDatabaseName();
    }

    /**
     * @param  list<array{int, int}>  $items  pares [prescripcion_item_id, cantidad]
     */
    public static function dispensar(float $barrera, int $usuarioId, int $pacienteId, int $prescripcionId, int $bodegaId, array $items, string $llave): Closure
    {
        return fn () => self::ejecutar($barrera, function () use ($usuarioId, $pacienteId, $prescripcionId, $bodegaId, $items, $llave) {
            $resultado = app(RegistrarDispensacion::class)->ejecutar(
                User::query()->findOrFail($usuarioId),
                new DatosDispensacion($pacienteId, $prescripcionId, $bodegaId, array_map(
                    fn (array $par) => ['prescripcion_item_id' => $par[0], 'cantidad' => $par[1]], $items,
                )),
                $llave,
            );

            return ['id' => $resultado->dispensacion->id, 'repetida' => $resultado->repetida];
        });
    }

    public static function aprobar(float $barrera, int $usuarioId, int $trasladoId): Closure
    {
        return fn () => self::ejecutar($barrera, fn () => [
            'id' => app(AprobarTraslado::class)->ejecutar(User::query()->findOrFail($usuarioId), Traslado::query()->findOrFail($trasladoId))->id,
        ]);
    }

    public static function despachar(float $barrera, int $usuarioId, int $trasladoId): Closure
    {
        return fn () => self::ejecutar($barrera, fn () => [
            'id' => app(DespacharTraslado::class)->ejecutar(User::query()->findOrFail($usuarioId), Traslado::query()->findOrFail($trasladoId))->id,
        ]);
    }

    /**
     * @param  array<int, int>  $recibidas
     */
    public static function recibir(float $barrera, int $usuarioId, int $trasladoId, array $recibidas): Closure
    {
        return fn () => self::ejecutar($barrera, fn () => [
            'id' => app(RecibirTraslado::class)->ejecutar(User::query()->findOrFail($usuarioId), Traslado::query()->findOrFail($trasladoId), $recibidas)->id,
        ]);
    }

    /**
     * @param  Closure(): array<string, mixed>  $tarea
     * @return array<string, mixed>
     */
    private static function ejecutar(float $barrera, Closure $tarea): array
    {
        usleep(max(0, (int) (($barrera - microtime(true)) * 1_000_000)));

        try {
            return ['ok' => true, ...$tarea()];
        } catch (ReglaNegocioException $e) {
            return ['ok' => false, 'codigo' => $e->codigo];
        }
    }
}
