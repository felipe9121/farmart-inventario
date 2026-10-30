<?php

declare(strict_types=1);

use App\Models\Paciente;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\postJson;

/**
 * RN-10: un error inesperado de BD con datos de un paciente no debe llevarlos al log.
 */
it('no escribe datos del paciente en el log ante un error de base de datos', function () {
    Paciente::factory()->create(['numero_documento' => '9012345678', 'nombres' => 'Rosalba']);
    Route::post('/api/_prueba/duplicar', fn () => Paciente::factory()->create([
        'numero_documento' => '9012345678', 'nombres' => 'Rosalba',
    ]));

    $registrado = [];
    Event::listen(MessageLogged::class, function (MessageLogged $evento) use (&$registrado) {
        $registrado[] = $evento->message.' '.json_encode(array_map(
            fn ($valor) => $valor instanceof Throwable ? (string) $valor : $valor,
            $evento->context,
        ), JSON_UNESCAPED_UNICODE);
    });

    postJson('/api/_prueba/duplicar')->assertServerError();

    $log = implode("\n", $registrado);
    expect($log)->toContain('uq_pacientes_documento')
        ->and($log)->not()->toContain('9012345678')
        ->and($log)->not()->toContain('Rosalba');
});

it('las trazas de excepciones no incluyen argumentos', function () {
    expect(ini_get('zend.exception_ignore_args'))->toBe('1');
});
