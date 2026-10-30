<?php

declare(strict_types=1);

use App\Http\Controllers\Api\DispensacionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('dispensaciones', [DispensacionController::class, 'store'])->middleware('idempotencia');
    Route::get('dispensaciones/{dispensacion}', [DispensacionController::class, 'show']);
});
