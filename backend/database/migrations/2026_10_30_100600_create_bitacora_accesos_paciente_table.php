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
        // RN-10: quién consultó datos de qué paciente y cuándo. Solo IDs: nunca se guardan
        // datos personales ni el término buscado.
        Schema::create('bitacora_accesos_paciente', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users');
            // Rol al momento del acceso: si luego cambia, la bitácora conserva el contexto.
            $table->string('rol', 30);
            // Nulo en búsquedas sin resultado.
            $table->foreignId('paciente_id')->nullable()->constrained('pacientes');
            $table->string('accion', 30);
            $table->string('ip', 45)->nullable();
            $table->dateTime('created_at', 3)->useCurrent();

            $table->index(['paciente_id', 'created_at'], 'ix_bitacora_accesos_paciente_fecha');
            $table->index(['usuario_id', 'created_at'], 'ix_bitacora_accesos_usuario_fecha');
        });

        DB::statement("ALTER TABLE bitacora_accesos_paciente ADD CONSTRAINT ck_bitacora_accesos_accion CHECK (accion IN (
            N'BUSQUEDA', N'CONSULTA_DETALLE', N'CONSULTA_PRESCRIPCIONES', N'DISPENSACION'))");
        DB::statement("ALTER TABLE bitacora_accesos_paciente ADD CONSTRAINT ck_bitacora_accesos_rol CHECK (rol IN (
            N'auxiliar_farmacia', N'regente_farmacia', N'medico', N'auditor', N'admin'))");

        // Una bitácora editable no sirve como evidencia: misma protección que el kardex.
        DB::unprepared("CREATE TRIGGER tr_bitacora_accesos_paciente_no_update ON bitacora_accesos_paciente INSTEAD OF UPDATE AS
            BEGIN
                SET NOCOUNT ON;
                THROW 51010, N'bitacora_accesos_paciente es inmutable.', 1;
            END");
        DB::unprepared("CREATE TRIGGER tr_bitacora_accesos_paciente_no_delete ON bitacora_accesos_paciente INSTEAD OF DELETE AS
            BEGIN
                SET NOCOUNT ON;
                THROW 51011, N'bitacora_accesos_paciente es inmutable.', 1;
            END");
    }

    public function down(): void
    {
        Schema::dropIfExists('bitacora_accesos_paciente');
    }
};
