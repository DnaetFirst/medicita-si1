<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Catálogo de exámenes de laboratorio
        Schema::create('examenes_laboratorio', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->string('nombre', 150);
            $table->string('categoria', 100)->default('General');
            $table->text('descripcion')->nullable();
            $table->string('unidad_medida', 50)->nullable();
            $table->text('valores_referencia')->nullable();
            $table->unsignedInteger('tiempo_estimado_horas')->default(24);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // 2. Órdenes de laboratorio (solicitadas por médico)
        Schema::create('ordenes_laboratorio', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo_orden', 50)->unique();
            $table->foreignId('paciente_id')->constrained('pacientes')->restrictOnDelete();
            $table->foreignId('medico_id')->constrained('medicos')->restrictOnDelete();
            $table->foreignId('atencion_medica_id')->nullable()->constrained('atenciones_medicas')->restrictOnDelete();
            $table->foreignId('cita_id')->nullable()->constrained('citas')->restrictOnDelete();
            $table->string('estado', 50)->default('emitida'); // emitida, en_proceso, finalizada, cancelada
            $table->text('motivo_cancelacion')->nullable();
            $table->foreignId('cancelado_por_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('observaciones')->nullable();
            $table->uuid('operacion_id')->unique();
            $table->timestamps();
        });

        // 3. Exámenes incluidos en cada orden
        Schema::create('ordenes_examenes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('orden_laboratorio_id')->constrained('ordenes_laboratorio')->cascadeOnDelete();
            $table->foreignId('examen_laboratorio_id')->constrained('examenes_laboratorio')->restrictOnDelete();
            $table->string('estado', 50)->default('pendiente'); // pendiente, en_proceso, informado
            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->unique(['orden_laboratorio_id', 'examen_laboratorio_id'], 'orden_examen_unique');
        });

        // 4. Resultados de laboratorio (emitidos por personal LAB)
        Schema::create('resultados_laboratorio', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo_resultado', 50)->unique();
            $table->foreignId('orden_laboratorio_id')->constrained('ordenes_laboratorio')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete(); // autor LAB
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('corrige_id')->nullable()->constrained('resultados_laboratorio')->restrictOnDelete();
            $table->text('motivo_correccion')->nullable();
            $table->string('estado', 50)->default('borrador'); // borrador, validado, publicado
            $table->timestamp('fecha_validacion')->nullable();
            $table->foreignId('validado_por_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('fecha_publicacion')->nullable();
            $table->foreignId('publicado_por_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('archivo_pdf_path')->nullable();
            $table->text('observaciones')->nullable();
            $table->uuid('operacion_id')->unique();
            $table->timestamps();
        });

        // 5. Detalles o valores cuantificados de los resultados
        Schema::create('detalles_resultado', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('resultado_laboratorio_id')->constrained('resultados_laboratorio')->cascadeOnDelete();
            $table->foreignId('examen_laboratorio_id')->constrained('examenes_laboratorio')->restrictOnDelete();
            $table->string('parametro', 100);
            $table->string('valor', 255);
            $table->string('unidad_medida', 50)->nullable();
            $table->string('rango_referencia', 100)->nullable();
            $table->string('interpretacion', 50)->nullable(); // Normal, Bajo, Alto, Crítico
            $table->timestamps();
        });

        // 6. Lista de espera (CU18)
        Schema::create('listas_espera', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('paciente_id')->constrained('pacientes')->restrictOnDelete();
            $table->foreignId('especialidad_id')->constrained('especialidades')->restrictOnDelete();
            $table->foreignId('medico_id')->nullable()->constrained('medicos')->restrictOnDelete();
            $table->date('fecha_desde');
            $table->date('fecha_hasta');
            $table->string('franja_horaria', 50)->default('cualquiera'); // manana, tarde, cualquiera
            $table->unsignedInteger('anticipacion_minima_horas')->default(2);
            $table->foreignId('cita_id')->nullable()->constrained('citas')->restrictOnDelete(); // cita previa a adelantar
            $table->string('estado', 50)->default('activa'); // activa, atendida, cancelada, expirada
            $table->text('motivo_cancelacion')->nullable();
            $table->date('fecha_inscripcion');
            $table->uuid('operacion_id')->unique();
            $table->timestamps();
            $table->index(['especialidad_id', 'estado', 'created_at'], 'idx_espera_esp_estado');
        });

        // 7. Ofertas exclusivas de cupo (CU18)
        Schema::create('ofertas_cupo', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lista_espera_id')->constrained('listas_espera')->restrictOnDelete();
            $table->foreignId('slot_id')->constrained('slots')->restrictOnDelete();
            $table->string('estado', 50)->default('pendiente'); // pendiente, aceptada, rechazada, vencida, anulada
            $table->timestamp('fecha_oferta');
            $table->timestamp('fecha_vencimiento');
            $table->timestamp('respondida_at')->nullable();
            $table->text('motivo_respuesta')->nullable();
            $table->uuid('operacion_id')->unique();
            $table->timestamps();
        });

        // 8. Referencia diferida en citas hacia ofertas de cupo y flag de recuperación
        Schema::table('citas', function (Blueprint $table): void {
            $table->foreignId('oferta_cupo_id')->nullable()->constrained('ofertas_cupo')->restrictOnDelete();
            $table->boolean('es_cupo_recuperado')->default(false);
        });

        // Re-establecer el índice condicional para SQLite y PostgreSQL (evita que la recreación de tabla elimine la cláusula WHERE)
        DB::statement('DROP INDEX IF EXISTS citas_active_slot_unique');
        DB::statement("CREATE UNIQUE INDEX citas_active_slot_unique ON citas (slot_id) WHERE estado NOT IN ('cancelada', 'postergada')");
    }

    public function down(): void
    {
        Schema::table('citas', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('oferta_cupo_id');
            $table->dropColumn('es_cupo_recuperado');
        });

        Schema::dropIfExists('ofertas_cupo');
        Schema::dropIfExists('listas_espera');
        Schema::dropIfExists('detalles_resultado');
        Schema::dropIfExists('resultados_laboratorio');
        Schema::dropIfExists('ordenes_examenes');
        Schema::dropIfExists('ordenes_laboratorio');
        Schema::dropIfExists('examenes_laboratorio');
    }
};
