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
        // RN-01: una existencia es bodega + producto + lote. producto_id se guarda
        // (redundante con el lote) para consultas FEFO por bodega/producto sin JOIN,
        // y la FK compuesta impide que no coincida con el producto del lote.
        Schema::create('existencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bodega_id')->constrained('bodegas');
            $table->unsignedBigInteger('producto_id');
            $table->unsignedBigInteger('lote_id');
            // Unidades mínimas de dispensación (enteras).
            $table->integer('cantidad')->default(0);
            $table->timestamps();

            $table->foreign(['lote_id', 'producto_id'], 'fk_existencias_lote_producto')
                ->references(['id', 'producto_id'])->on('lotes');
            $table->foreign('producto_id', 'fk_existencias_producto')->references('id')->on('productos');
            $table->unique(['bodega_id', 'lote_id'], 'uq_existencias_bodega_lote');
            $table->index(['bodega_id', 'producto_id'], 'ix_existencias_bodega_producto');
        });

        // RN-03: última barrera contra stock negativo, aunque falle la lógica de la app.
        DB::statement('ALTER TABLE existencias ADD CONSTRAINT ck_existencias_cantidad CHECK (cantidad >= 0)');

        // RN-11: el mínimo es por bodega, no global por producto.
        Schema::create('stock_minimos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bodega_id')->constrained('bodegas');
            $table->foreignId('producto_id')->constrained('productos');
            $table->integer('cantidad_minima');
            $table->timestamps();

            $table->unique(['bodega_id', 'producto_id'], 'uq_stock_minimos_bodega_producto');
        });

        DB::statement('ALTER TABLE stock_minimos ADD CONSTRAINT ck_stock_minimos_cantidad CHECK (cantidad_minima >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_minimos');
        Schema::dropIfExists('existencias');
    }
};
