<?php

declare(strict_types=1);

namespace App\Providers;

use App\Policies\AlertaPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Fuerza bruta: 5 intentos por minuto por correo + IP.
        // RN-11: alertas y asistente (solo datos de inventario).
        Gate::define('ver-alertas', [AlertaPolicy::class, 'puedeVer']);

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()));
    }
}
