<?php

declare(strict_types=1);

use App\Enums\Rol;
use App\Models\Existencia;
use App\Models\MovimientoKardex;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\seed;

it('siembra datos coherentes: cada existencia cuadra con su kardex', function () {
    seed(DatabaseSeeder::class);

    expect(User::query()->where('rol', Rol::RegenteFarmacia)->count())->toBe(2)
        ->and(Existencia::query()->count())->toBeGreaterThan(0)
        ->and(MovimientoKardex::query()->count())->toBe(Existencia::query()->count());

    $descuadres = DB::table('existencias as e')
        ->leftJoin(
            DB::raw('(SELECT bodega_id, lote_id, SUM(cantidad) AS total FROM movimientos_kardex GROUP BY bodega_id, lote_id) k'),
            fn ($join) => $join->on('k.bodega_id', '=', 'e.bodega_id')->on('k.lote_id', '=', 'e.lote_id'),
        )
        ->whereRaw('COALESCE(k.total, 0) <> e.cantidad')
        ->count();

    expect($descuadres)->toBe(0);
});
