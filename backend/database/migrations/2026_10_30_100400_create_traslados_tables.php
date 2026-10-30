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
        Schema::create('traslados', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 30)->unique('uq_traslados_codigo');
            $table->foreignId('bodega_origen_id')->constrained('bodegas');
            $table->foreignId('bodega_destino_id')->constrained('bodegas');
            $table->string('estado', 20)->default('BORRADOR');
            $table->foreignId('creado_por')->constrained('users');
            // Un par (usuario, fecha) por cada transición: trazabilidad de quién hizo qué.
            $table->foreignId('solicitado_por')->nullable()->constrained('users');
            $table->dateTime('solicitado_at')->nullable();
            $table->foreignId('aprobado_por')->nullable()->constrained('users');
            $table->dateTime('aprobado_at')->nullable();
            $table->foreignId('despachado_por')->nullable()->constrained('users');
            $table->dateTime('despachado_at')->nullable();
            $table->foreignId('recibido_por')->nullable()->constrained('users');
            $table->dateTime('recibido_at')->nullable();
            $table->foreignId('anulado_por')->nullable()->constrained('users');
            $table->dateTime('anulado_at')->nullable();
            $table->string('motivo_anulacion', 500)->nullable();
            $table->string('observaciones', 500)->nullable();
            $table->timestamps();

            $table->index(['estado'], 'ix_traslados_estado');
        });

        DB::statement("ALTER TABLE traslados ADD CONSTRAINT ck_traslados_estado CHECK (estado IN (
            N'BORRADOR', N'SOLICITADO', N'APROBADO', N'EN_TRANSITO', N'RECIBIDO', N'RECIBIDO_PARCIAL', N'ANULADO'))");
        DB::statement('ALTER TABLE traslados ADD CONSTRAINT ck_traslados_bodegas_distintas CHECK (bodega_origen_id <> bodega_destino_id)');
        // RN-08: quien solicita no puede aprobar (y no se aprueba sin solicitud).
        DB::statement('ALTER TABLE traslados ADD CONSTRAINT ck_traslados_aprobador_distinto
            CHECK (aprobado_por IS NULL OR (solicitado_por IS NOT NULL AND aprobado_por <> solicitado_por))');
        // RN-07: cada estado exige los responsables de las etapas por las que pasó.
        DB::statement("ALTER TABLE traslados ADD CONSTRAINT ck_traslados_estado_coherente CHECK (
            (estado NOT IN (N'SOLICITADO', N'APROBADO', N'EN_TRANSITO', N'RECIBIDO', N'RECIBIDO_PARCIAL') OR solicitado_por IS NOT NULL)
            AND (estado NOT IN (N'APROBADO', N'EN_TRANSITO', N'RECIBIDO', N'RECIBIDO_PARCIAL') OR aprobado_por IS NOT NULL)
            AND (estado NOT IN (N'EN_TRANSITO', N'RECIBIDO', N'RECIBIDO_PARCIAL') OR despachado_por IS NOT NULL)
            AND (estado NOT IN (N'RECIBIDO', N'RECIBIDO_PARCIAL') OR recibido_por IS NOT NULL)
        )");
        // Solo se anula antes del despacho: después el stock ya salió del origen.
        // IS NOT NULL explícito: LEN(NULL) > 0 es UNKNOWN y un CHECK solo rechaza FALSE.
        DB::statement("ALTER TABLE traslados ADD CONSTRAINT ck_traslados_anulacion CHECK (
            estado <> N'ANULADO' OR (anulado_por IS NOT NULL AND motivo_anulacion IS NOT NULL
                AND LEN(motivo_anulacion) > 0 AND despachado_por IS NULL)
        )");

        // Cada ítem es un lote concreto: el lote se elige al solicitar (supuesto documentado).
        Schema::create('traslado_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('traslado_id')->constrained('traslados');
            $table->foreignId('lote_id')->constrained('lotes');
            $table->integer('cantidad_solicitada');
            $table->integer('cantidad_despachada')->nullable();
            $table->integer('cantidad_recibida')->nullable();
            // RN-07: lo despachado y no recibido es discrepancia pendiente.
            $table->computed('cantidad_discrepancia', 'cantidad_despachada - cantidad_recibida')->persisted();
            $table->timestamps();

            $table->unique(['traslado_id', 'lote_id'], 'uq_traslado_items_lote');
        });

        DB::statement('ALTER TABLE traslado_items ADD CONSTRAINT ck_traslado_items_cantidad_solicitada CHECK (cantidad_solicitada > 0)');
        DB::statement('ALTER TABLE traslado_items ADD CONSTRAINT ck_traslado_items_cantidad_despachada
            CHECK (cantidad_despachada IS NULL OR (cantidad_despachada >= 0 AND cantidad_despachada <= cantidad_solicitada))');
        DB::statement('ALTER TABLE traslado_items ADD CONSTRAINT ck_traslado_items_cantidad_recibida
            CHECK (cantidad_recibida IS NULL OR (cantidad_despachada IS NOT NULL AND cantidad_recibida >= 0 AND cantidad_recibida <= cantidad_despachada))');
    }

    public function down(): void
    {
        Schema::dropIfExists('traslado_items');
        Schema::dropIfExists('traslados');
    }
};
