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
        Schema::create('dispensaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescripcion_id')->constrained('prescripciones');
            $table->foreignId('bodega_id')->constrained('bodegas');
            $table->foreignId('dispensado_por')->constrained('users');
            // RN-05: segundo usuario (regente) que autoriza productos de control especial.
            $table->foreignId('autorizado_por')->nullable()->constrained('users');
            $table->dateTime('autorizado_at')->nullable();
            // RN-09: llave del header Idempotency-Key y hash del payload, para detectar
            // la misma llave reutilizada con un cuerpo distinto.
            $table->string('idempotency_key', 100)->nullable();
            $table->char('idempotency_hash', 64)->nullable();
            $table->timestamps();

            $table->index(['prescripcion_id'], 'ix_dispensaciones_prescripcion');
        });

        // UNIQUE filtrado: en SQL Server un UNIQUE normal admite un solo NULL; el filtro
        // permite dispensaciones sin llave (procesos internos) y unicidad cuando la hay.
        DB::statement('CREATE UNIQUE INDEX uq_dispensaciones_idempotencia
            ON dispensaciones (dispensado_por, idempotency_key) WHERE idempotency_key IS NOT NULL');
        // RN-05: quien dispensa no puede autorizarse a sí mismo.
        DB::statement('ALTER TABLE dispensaciones ADD CONSTRAINT ck_dispensaciones_autorizador_distinto
            CHECK (autorizado_por IS NULL OR autorizado_por <> dispensado_por)');
        DB::statement('ALTER TABLE dispensaciones ADD CONSTRAINT ck_dispensaciones_autorizacion_completa
            CHECK ((autorizado_por IS NULL AND autorizado_at IS NULL) OR (autorizado_por IS NOT NULL AND autorizado_at IS NOT NULL))');
        DB::statement('ALTER TABLE dispensaciones ADD CONSTRAINT ck_dispensaciones_idempotencia
            CHECK ((idempotency_key IS NULL AND idempotency_hash IS NULL) OR (LEN(idempotency_key) > 0 AND idempotency_hash IS NOT NULL))');

        // RN-02: un ítem prescrito puede salir de varios lotes (FEFO): una fila por lote.
        Schema::create('dispensacion_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispensacion_id')->constrained('dispensaciones');
            $table->foreignId('prescripcion_item_id')->constrained('prescripcion_items');
            $table->foreignId('lote_id')->constrained('lotes');
            $table->integer('cantidad');
            $table->timestamps();

            $table->unique(['dispensacion_id', 'prescripcion_item_id', 'lote_id'], 'uq_dispensacion_items_lote');
        });

        DB::statement('ALTER TABLE dispensacion_items ADD CONSTRAINT ck_dispensacion_items_cantidad CHECK (cantidad > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('dispensacion_items');
        Schema::dropIfExists('dispensaciones');
    }
};
