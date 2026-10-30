<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AjusteController;
use App\Http\Controllers\Api\DispensacionController;
use App\Http\Controllers\Api\KardexController;
use App\Http\Controllers\Api\TrasladoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
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
