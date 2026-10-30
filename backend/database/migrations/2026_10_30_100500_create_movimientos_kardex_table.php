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
        // RN-06: todo cambio de stock deja un movimiento. Las correcciones son
        // nuevos movimientos de ajuste con motivo, nunca ediciones.
        Schema::create('movimientos_kardex', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bodega_id')->constrained('bodegas');
            $table->unsignedBigInteger('producto_id');
            $table->unsignedBigInteger('lote_id');
            $table->string('tipo', 20);
            // Con signo: positivo entra, negativo sale.
            $table->integer('cantidad');
            // Saldo de la existencia tras el movimiento: permite auditar sin recalcular la historia.
            $table->integer('saldo_resultante');
            $table->string('motivo', 500)->nullable();
            $table->string('documento_referencia', 50)->nullable();
            // FKs explícitas (no polimórficas) para tener integridad referencial en la BD.
            $table->foreignId('dispensacion_item_id')->nullable()->constrained('dispensacion_items');
            $table->foreignId('traslado_item_id')->nullable()->constrained('traslado_items');
            $table->foreignId('usuario_id')->constrained('users');
            $table->dateTime('created_at', 3)->useCurrent();

            $table->foreign(['lote_id', 'producto_id'], 'fk_movimientos_kardex_lote_producto')
                ->references(['id', 'producto_id'])->on('lotes');
            $table->foreign('producto_id', 'fk_movimientos_kardex_producto')->references('id')->on('productos');
            $table->index(['bodega_id', 'lote_id', 'id'], 'ix_movimientos_kardex_bodega_lote');
            $table->index(['producto_id', 'created_at'], 'ix_movimientos_kardex_producto_fecha');
        });

        DB::statement("ALTER TABLE movimientos_kardex ADD CONSTRAINT ck_movimientos_kardex_tipo CHECK (tipo IN (
            N'ENTRADA', N'DISPENSACION', N'TRASLADO_SALIDA', N'TRASLADO_ENTRADA', N'AJUSTE_POSITIVO', N'AJUSTE_NEGATIVO'))");
        DB::statement("ALTER TABLE movimientos_kardex ADD CONSTRAINT ck_movimientos_kardex_signo CHECK (
            (tipo IN (N'ENTRADA', N'TRASLADO_ENTRADA', N'AJUSTE_POSITIVO') AND cantidad > 0)
            OR (tipo IN (N'DISPENSACION', N'TRASLADO_SALIDA', N'AJUSTE_NEGATIVO') AND cantidad < 0))");
        DB::statement('ALTER TABLE movimientos_kardex ADD CONSTRAINT ck_movimientos_kardex_saldo CHECK (saldo_resultante >= 0)');
        DB::statement("ALTER TABLE movimientos_kardex ADD CONSTRAINT ck_movimientos_kardex_motivo_ajuste CHECK (
            tipo NOT IN (N'AJUSTE_POSITIVO', N'AJUSTE_NEGATIVO') OR (motivo IS NOT NULL AND LEN(motivo) > 0))");
        // Cada tipo apunta exactamente al documento que lo origina.
        DB::statement("ALTER TABLE movimientos_kardex ADD CONSTRAINT ck_movimientos_kardex_referencia CHECK (
            (tipo = N'DISPENSACION' AND dispensacion_item_id IS NOT NULL AND traslado_item_id IS NULL)
            OR (tipo IN (N'TRASLADO_SALIDA', N'TRASLADO_ENTRADA') AND traslado_item_id IS NOT NULL AND dispensacion_item_id IS NULL)
            OR (tipo IN (N'ENTRADA', N'AJUSTE_POSITIVO', N'AJUSTE_NEGATIVO') AND dispensacion_item_id IS NULL AND traslado_item_id IS NULL))");

        // UNIQUE filtrados: un ítem de dispensación genera un solo movimiento, y un ítem de
        // traslado una sola salida y una sola entrada (evita despachar o recibir dos veces).
        DB::statement('CREATE UNIQUE INDEX uq_movimientos_kardex_dispensacion_item
            ON movimientos_kardex (dispensacion_item_id) WHERE dispensacion_item_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX uq_movimientos_kardex_traslado_item_tipo
            ON movimientos_kardex (traslado_item_id, tipo) WHERE traslado_item_id IS NOT NULL');

        // Inmutabilidad en la BD: ni la app ni un usuario con acceso directo pueden editar
        // o borrar movimientos. THROW aborta y revierte la transacción completa.
        DB::unprepared("CREATE TRIGGER tr_movimientos_kardex_no_update ON movimientos_kardex INSTEAD OF UPDATE AS
            BEGIN
                SET NOCOUNT ON;
                THROW 51000, N'movimientos_kardex es inmutable: registre un ajuste con motivo.', 1;
            END");
        DB::unprepared("CREATE TRIGGER tr_movimientos_kardex_no_delete ON movimientos_kardex INSTEAD OF DELETE AS
            BEGIN
                SET NOCOUNT ON;
                THROW 51001, N'movimientos_kardex es inmutable: no se permite eliminar movimientos.', 1;
            END");
    }

    public function down(): void
    {
        // Los triggers se eliminan junto con la tabla.
        Schema::dropIfExists('movimientos_kardex');
    }
};
