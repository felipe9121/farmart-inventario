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
        // RN-10: datos personales sensibles. El acceso por rol, el enmascarado y la
        // bitácora se aplican en la capa de aplicación (etapa de seguridad).
        Schema::create('pacientes', function (Blueprint $table) {
            $table->id();
            $table->string('tipo_documento', 3);
            $table->string('numero_documento', 20);
            $table->string('nombres', 100);
            $table->string('apellidos', 100);
            $table->date('fecha_nacimiento');
            $table->string('telefono', 20)->nullable();
            $table->timestamps();

            $table->unique(['tipo_documento', 'numero_documento'], 'uq_pacientes_documento');
        });

        DB::statement("ALTER TABLE pacientes ADD CONSTRAINT ck_pacientes_tipo_documento
            CHECK (tipo_documento IN (N'CC', N'TI', N'RC', N'CE', N'PA', N'PPT'))");
        DB::statement('ALTER TABLE pacientes ADD CONSTRAINT ck_pacientes_numero_documento CHECK (LEN(numero_documento) > 0)');

        Schema::create('prescripciones', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 30)->unique('uq_prescripciones_numero');
            $table->foreignId('paciente_id')->constrained('pacientes');
            $table->foreignId('medico_id')->constrained('users');
            $table->date('fecha_emision');
            // RN-04: solo se dispensa si hoy está entre fecha_emision y vigente_hasta.
            $table->date('vigente_hasta');
            $table->string('observaciones', 500)->nullable();
            $table->timestamps();

            $table->index(['paciente_id', 'vigente_hasta'], 'ix_prescripciones_paciente_vigencia');
        });

        DB::statement('ALTER TABLE prescripciones ADD CONSTRAINT ck_prescripciones_vigencia CHECK (vigente_hasta >= fecha_emision)');

        Schema::create('prescripcion_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescripcion_id')->constrained('prescripciones');
            $table->foreignId('producto_id')->constrained('productos');
            $table->integer('cantidad_prescrita');
            // Acumulado de dispensaciones parciales (RN-04). Se actualiza en la misma
            // transacción que la dispensación, con el ítem bloqueado.
            $table->integer('cantidad_dispensada')->default(0);
            $table->string('posologia', 300)->nullable();
            $table->timestamps();

            $table->unique(['prescripcion_id', 'producto_id'], 'uq_prescripcion_items_producto');
        });

        DB::statement('ALTER TABLE prescripcion_items ADD CONSTRAINT ck_prescripcion_items_cantidad_prescrita CHECK (cantidad_prescrita > 0)');
        // RN-04: la BD impide dispensar más de lo prescrito aunque falle la validación de la app.
        DB::statement('ALTER TABLE prescripcion_items ADD CONSTRAINT ck_prescripcion_items_cantidad_dispensada
            CHECK (cantidad_dispensada >= 0 AND cantidad_dispensada <= cantidad_prescrita)');
    }

    public function down(): void
    {
        Schema::dropIfExists('prescripcion_items');
        Schema::dropIfExists('prescripciones');
        Schema::dropIfExists('pacientes');
    }
};
