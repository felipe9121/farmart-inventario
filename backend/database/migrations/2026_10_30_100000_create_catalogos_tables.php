<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bodegas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique('uq_bodegas_codigo');
            $table->string('nombre', 150);
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 30)->unique('uq_productos_codigo');
            $table->string('nombre', 200);
            $table->string('presentacion', 100);
            $table->string('unidad_medida', 30);
            // RN-05: dispensar un producto de control especial exige autorización de un segundo regente.
            $table->boolean('es_control_especial')->default(false);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('lotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos');
            $table->string('numero_lote', 50);
            $table->date('fecha_vencimiento');
            $table->timestamps();

            $table->unique(['producto_id', 'numero_lote'], 'uq_lotes_producto_numero');
            // Destino de FKs compuestas (lote_id, producto_id): garantiza que el producto
            // de una existencia o movimiento sea el mismo del lote.
            $table->unique(['id', 'producto_id'], 'uq_lotes_id_producto');
        });

        DB::statement('ALTER TABLE bodegas ADD CONSTRAINT ck_bodegas_codigo CHECK (LEN(codigo) > 0)');
        DB::statement('ALTER TABLE productos ADD CONSTRAINT ck_productos_codigo CHECK (LEN(codigo) > 0)');
        DB::statement('ALTER TABLE lotes ADD CONSTRAINT ck_lotes_numero CHECK (LEN(numero_lote) > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('lotes');
        Schema::dropIfExists('productos');
        Schema::dropIfExists('bodegas');
    }
};
