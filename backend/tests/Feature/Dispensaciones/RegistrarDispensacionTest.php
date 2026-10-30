<?php

declare(strict_types=1);

use App\Actions\Dispensaciones\CredencialesAutorizacion;
use App\Actions\Dispensaciones\RegistrarDispensacion;
use App\Actions\Dispensaciones\ResultadoDispensacion;
use App\Enums\Rol;
use App\Enums\TipoMovimiento;
use App\Models\Dispensacion;
use App\Models\MovimientoKardex;
use App\Models\Paciente;
use App\Models\Prescripcion;
use App\Models\PrescripcionItem;
use App\Models\User;
use Tests\Support\EscenarioDispensacion;

/**
 * @param  list<array{PrescripcionItem, int}>  $items
 */
function dispensar(EscenarioDispensacion $e, array $items, ?CredencialesAutorizacion $firma = null, string $llave = 'llave-1', ?User $usuario = null): ResultadoDispensacion
{
    return app(RegistrarDispensacion::class)->ejecutar($usuario ?? $e->auxiliar, $e->datos($items, $firma), $llave);
}

it('dispensa, descuenta stock, registra kardex y acumula lo dispensado', function () {
    $e = EscenarioDispensacion::crear();
    $item = $e->item(prescrita: 10);
    $existencia = $e->stock($item, 50);

    $resultado = dispensar($e, [[$item, 4]]);

    $movimiento = MovimientoKardex::query()->sole();
    expect($resultado->repetida)->toBeFalse()
        ->and($resultado->dispensacion->items)->toHaveCount(1)
        ->and($existencia->refresh()->cantidad)->toBe(46)
        ->and($item->refresh()->cantidad_dispensada)->toBe(4)
        ->and($movimiento->tipo)->toBe(TipoMovimiento::Dispensacion)
        ->and($movimiento->cantidad)->toBe(-4)
        ->and($movimiento->saldo_resultante)->toBe(46)
        ->and($movimiento->dispensacion_item_id)->toBe($resultado->dispensacion->items->sole()->id);
});

it('toma de varios lotes por FEFO con una fila y un movimiento por lote (RN-02)', function () {
    $e = EscenarioDispensacion::crear();
    $item = $e->item(prescrita: 10);
    $tarde = $e->stock($item, 50, diasParaVencer: 300);
    $pronto = $e->stock($item, 3, diasParaVencer: 15);
    $e->stock($item, 100, diasParaVencer: -1);

    $resultado = dispensar($e, [[$item, 5]]);

    expect($resultado->dispensacion->items->pluck('cantidad', 'lote_id')->all())
        ->toBe([$pronto->lote_id => 3, $tarde->lote_id => 2])
        ->and(MovimientoKardex::query()->count())->toBe(2)
        ->and($pronto->refresh()->cantidad)->toBe(0);
});

it('permite parciales acumuladas pero no exceder lo prescrito (RN-04)', function () {
    $e = EscenarioDispensacion::crear();
    $item = $e->item(prescrita: 10);
    $e->stock($item, 50);

    dispensar($e, [[$item, 4]], llave: 'a');
    dispensar($e, [[$item, 6]], llave: 'b');
    $error = capturarError(fn () => dispensar($e, [[$item, 1]], llave: 'c'));

    expect($item->refresh()->cantidad_dispensada)->toBe(10)
        ->and($error->codigo)->toBe('EXCEDE_LO_PRESCRITO')
        ->and($error->detalle)->toMatchArray(['solicitado' => 1, 'pendiente' => 0]);
});

it('rechaza una prescripción vencida', function () {
    $e = EscenarioDispensacion::crear();
    $item = $e->item();
    $e->stock($item, 50);
    $e->prescripcion->update(['fecha_emision' => now()->subDays(40), 'vigente_hasta' => now()->subDays(10)]);

    expect(capturarError(fn () => dispensar($e, [[$item, 1]]))->codigo)->toBe('PRESCRIPCION_NO_VIGENTE');
});

it('rechaza si la prescripción es de otro paciente', function () {
    $e = EscenarioDispensacion::crear();
    $item = $e->item();
    $e->stock($item, 50);
    $e->prescripcion->update(['paciente_id' => Paciente::factory()->create()->id]);

    expect(capturarError(fn () => dispensar($e, [[$item, 1]]))->codigo)->toBe('PRESCRIPCION_DE_OTRO_PACIENTE');
});

it('rechaza ítems de otra prescripción', function () {
    $e = EscenarioDispensacion::crear();
    $ajeno = PrescripcionItem::factory()->create(['prescripcion_id' => Prescripcion::factory()]);

    expect(capturarError(fn () => dispensar($e, [[$ajeno, 1]]))->codigo)->toBe('ITEM_NO_PERTENECE_A_PRESCRIPCION');
});

it('rechaza sin dejar rastro si un ítem no tiene stock suficiente', function () {
    $e = EscenarioDispensacion::crear();
    $conStock = $e->item();
    $sinStock = $e->item();
    $existencia = $e->stock($conStock, 50);
    $e->stock($sinStock, 2);

    $error = capturarError(fn () => dispensar($e, [[$conStock, 5], [$sinStock, 3]]));

    expect($error->codigo)->toBe('STOCK_INSUFICIENTE')
        ->and($existencia->refresh()->cantidad)->toBe(50)
        ->and($conStock->refresh()->cantidad_dispensada)->toBe(0)
        ->and(Dispensacion::query()->count())->toBe(0)
        ->and(MovimientoKardex::query()->count())->toBe(0);
});

describe('control especial (RN-05)', function () {
    it('exige la autorización de un regente', function () {
        $e = EscenarioDispensacion::crear();
        $item = $e->item(controlEspecial: true);
        $e->stock($item, 50);

        expect(capturarError(fn () => dispensar($e, [[$item, 1]]))->codigo)->toBe('AUTORIZACION_REQUERIDA');
    });

    it('registra al regente que autoriza', function () {
        $e = EscenarioDispensacion::crear();
        $item = $e->item(controlEspecial: true);
        $e->stock($item, 50);

        $dispensacion = dispensar($e, [[$item, 1]], $e->firmaRegente())->dispensacion;

        expect($dispensacion->autorizado_por)->toBe($e->regente->id)
            ->and($dispensacion->autorizado_at)->not->toBeNull();
    });

    it('rechaza una clave incorrecta sin revelar si el usuario existe', function () {
        $e = EscenarioDispensacion::crear();
        $item = $e->item(controlEspecial: true);
        $e->stock($item, 50);

        $claveErronea = capturarError(fn () => dispensar($e, [[$item, 1]], new CredencialesAutorizacion($e->regente->email, 'otra')));
        $inexistente = capturarError(fn () => dispensar($e, [[$item, 1]], new CredencialesAutorizacion('nadie@farmart.test', 'password')));

        expect($claveErronea->codigo)->toBe('AUTORIZACION_INVALIDA')
            ->and($inexistente->codigo)->toBe('AUTORIZACION_INVALIDA')
            ->and($claveErronea->getMessage())->toBe($inexistente->getMessage());
    });

    it('rechaza a un autorizador que no es regente', function () {
        $e = EscenarioDispensacion::crear();
        $item = $e->item(controlEspecial: true);
        $e->stock($item, 50);
        $otroAuxiliar = User::factory()->rol(Rol::AuxiliarFarmacia)->create();

        $error = capturarError(fn () => dispensar($e, [[$item, 1]], new CredencialesAutorizacion($otroAuxiliar->email, 'password')));

        expect($error->codigo)->toBe('AUTORIZADOR_NO_REGENTE');
    });

    it('impide que un regente se autorice a sí mismo', function () {
        $e = EscenarioDispensacion::crear();
        $item = $e->item(controlEspecial: true);
        $e->stock($item, 50);

        $error = capturarError(fn () => dispensar($e, [[$item, 1]], $e->firmaRegente(), usuario: $e->regente));

        expect($error->codigo)->toBe('AUTORIZADOR_ES_DISPENSADOR');
    });

    it('rechaza a un regente inactivo', function () {
        $e = EscenarioDispensacion::crear();
        $item = $e->item(controlEspecial: true);
        $e->stock($item, 50);
        $e->regente->forceFill(['activo' => false])->save();

        expect(capturarError(fn () => dispensar($e, [[$item, 1]], $e->firmaRegente()))->codigo)->toBe('AUTORIZACION_INVALIDA');
    });

    it('bloquea la co-firma tras 5 intentos fallidos, aun con la clave correcta', function () {
        $e = EscenarioDispensacion::crear();
        $item = $e->item(controlEspecial: true);
        $e->stock($item, 50);
        $erronea = new CredencialesAutorizacion($e->regente->email, 'otra');

        foreach (range(1, 5) as $intento) {
            expect(capturarError(fn () => dispensar($e, [[$item, 1]], $erronea, llave: "k-{$intento}"))->codigo)->toBe('AUTORIZACION_INVALIDA');
        }
        $bloqueo = capturarError(fn () => dispensar($e, [[$item, 1]], $e->firmaRegente(), llave: 'k-6'));

        expect($bloqueo->codigo)->toBe('AUTORIZACION_BLOQUEADA')
            ->and($bloqueo->status)->toBe(429);
    });

    it('el bloqueo de un auxiliar no afecta a otro', function () {
        $e = EscenarioDispensacion::crear();
        $item = $e->item(controlEspecial: true);
        $e->stock($item, 50);
        $erronea = new CredencialesAutorizacion($e->regente->email, 'otra');
        foreach (range(1, 5) as $intento) {
            capturarError(fn () => dispensar($e, [[$item, 1]], $erronea, llave: "k-{$intento}"));
        }

        $otro = User::factory()->rol(Rol::AuxiliarFarmacia)->create();

        expect(dispensar($e, [[$item, 1]], $e->firmaRegente(), usuario: $otro)->dispensacion->autorizado_por)->toBe($e->regente->id);
    });

    it('no pide autorización si no hay productos de control especial', function () {
        $e = EscenarioDispensacion::crear();
        $item = $e->item();
        $e->stock($item, 50);

        expect(dispensar($e, [[$item, 1]])->dispensacion->autorizado_por)->toBeNull();
    });
});

describe('idempotencia (RN-09)', function () {
    it('devuelve la dispensación original al repetir la misma llave y contenido', function () {
        $e = EscenarioDispensacion::crear();
        $item = $e->item();
        $existencia = $e->stock($item, 50);

        $primera = dispensar($e, [[$item, 4]]);
        $segunda = dispensar($e, [[$item, 4]]);

        expect($segunda->repetida)->toBeTrue()
            ->and($segunda->dispensacion->id)->toBe($primera->dispensacion->id)
            ->and($existencia->refresh()->cantidad)->toBe(46)
            ->and(MovimientoKardex::query()->count())->toBe(1);
    });

    it('rechaza la misma llave con contenido distinto', function () {
        $e = EscenarioDispensacion::crear();
        $item = $e->item();
        $e->stock($item, 50);

        dispensar($e, [[$item, 4]]);

        expect(capturarError(fn () => dispensar($e, [[$item, 5]]))->codigo)->toBe('IDEMPOTENCY_KEY_REUTILIZADA');
    });

    it('trata la llave por usuario: otro usuario puede usar la misma', function () {
        $e = EscenarioDispensacion::crear();
        $item = $e->item();
        $e->stock($item, 50);
        $otro = User::factory()->rol(Rol::AuxiliarFarmacia)->create();

        dispensar($e, [[$item, 4]]);
        $resultado = dispensar($e, [[$item, 4]], usuario: $otro);

        expect($resultado->repetida)->toBeFalse()
            ->and($item->refresh()->cantidad_dispensada)->toBe(8);
    });

    it('no consume la llave si la dispensación falla', function () {
        $e = EscenarioDispensacion::crear();
        $item = $e->item();

        expect(capturarError(fn () => dispensar($e, [[$item, 4]]))->codigo)->toBe('STOCK_INSUFICIENTE');

        $e->stock($item, 50);
        expect(dispensar($e, [[$item, 4]])->repetida)->toBeFalse();
    });
});
