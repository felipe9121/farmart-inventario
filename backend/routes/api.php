<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AjusteController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DispensacionController;
use App\Http\Controllers\Api\KardexController;
use App\Http\Controllers\Api\PacienteController;
use App\Http\Controllers\Api\TrasladoController;
use Illuminate\Support\Facades\Route;

Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware(['auth:sanctum', 'activo'])->group(function () {
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);

    Route::get('pacientes', [PacienteController::class, 'buscar']);
    Route::get('pacientes/{paciente}', [PacienteController::class, 'show']);
    Route::get('pacientes/{paciente}/prescripciones', [PacienteController::class, 'prescripciones']);

    Route::post('dispensaciones', [DispensacionController::class, 'store'])->middleware('idempotencia');
    Route::get('dispensaciones/{dispensacion}', [DispensacionController::class, 'show']);

    Route::post('ajustes', [AjusteController::class, 'store']);
    Route::get('kardex', [KardexController::class, 'index']);

    Route::get('traslados', [TrasladoController::class, 'index']);
    Route::post('traslados', [TrasladoController::class, 'store']);
    Route::get('traslados/{traslado}', [TrasladoController::class, 'show']);
    Route::post('traslados/{traslado}/solicitar', [TrasladoController::class, 'solicitar']);
    Route::post('traslados/{traslado}/aprobar', [TrasladoController::class, 'aprobar']);
    Route::post('traslados/{traslado}/despachar', [TrasladoController::class, 'despachar']);
    Route::post('traslados/{traslado}/recibir', [TrasladoController::class, 'recibir']);
    Route::post('traslados/{traslado}/anular', [TrasladoController::class, 'anular']);
});
