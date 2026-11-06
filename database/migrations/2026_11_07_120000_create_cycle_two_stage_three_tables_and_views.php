<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Audit and execution log table for natural language AI analytical queries (CU17)
        Schema::create('reportes_ia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->text('pregunta_usuario');
            $table->text('sql_generado')->nullable();
            $table->boolean('es_valido')->default(false);
            $table->string('motivo_rechazo', 255)->nullable();
            $table->unsignedInteger('filas_retornadas')->default(0);
            $table->unsignedInteger('tiempo_ms')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        // 2. Read-only analytical view: vista_reporte_citas
        // Strictly excludes sensitive medical notes, anamnesis, and laboratory discrete values
        DB::statement('DROP VIEW IF EXISTS vista_reporte_citas');
        DB::statement("
            CREATE VIEW vista_reporte_citas AS
            SELECT 
                c.id AS cita_id,
                c.codigo_ficha,
                c.fecha AS fecha_cita,
                c.hora AS hora_cita,
                c.estado,
                c.motivo_cancelacion,
                c.cancelado_por_user_id,
                c.es_cupo_recuperado,
                c.hora_confirmacion,
                c.hora_llegada,
                c.hora_inicio_atencion,
                c.hora_fin_atencion,
                m.id AS medico_id,
                u_m.name AS medico_nombre,
                e.id AS especialidad_id,
                e.nombre AS especialidad_nombre,
                p.id AS paciente_id,
                (p.nombres || ' ' || p.apellidos) AS paciente_nombre,
                p.ci AS paciente_ci,
                c.created_at
            FROM citas c
            INNER JOIN medicos m ON c.medico_id = m.id
            INNER JOIN users u_m ON m.user_id = u_m.id
            INNER JOIN especialidades e ON m.especialidad_id = e.id
            INNER JOIN pacientes p ON c.paciente_id = p.id
        ");

        // 3. Read-only analytical view: vista_reporte_ocupacion
        DB::statement('DROP VIEW IF EXISTS vista_reporte_ocupacion');
        DB::statement("
            CREATE VIEW vista_reporte_ocupacion AS
            SELECT 
                s.fecha,
                m.id AS medico_id,
                u_m.name AS medico_nombre,
                e.id AS especialidad_id,
                e.nombre AS especialidad_nombre,
                COUNT(s.id) AS total_slots,
                SUM(CASE WHEN s.estado = 'reservado' THEN 1 ELSE 0 END) AS slots_reservados,
                SUM(CASE WHEN s.estado = 'disponible' THEN 1 ELSE 0 END) AS slots_disponibles,
                SUM(CASE WHEN s.estado = 'bloqueado' THEN 1 ELSE 0 END) AS slots_bloqueados
            FROM slots s
            INNER JOIN medicos m ON s.medico_id = m.id
            INNER JOIN users u_m ON m.user_id = u_m.id
            INNER JOIN especialidades e ON m.especialidad_id = e.id
            GROUP BY s.fecha, m.id, u_m.name, e.id, e.nombre
        ");

        // 4. Read-only analytical view: vista_reporte_cancelaciones
        DB::statement('DROP VIEW IF EXISTS vista_reporte_cancelaciones');
        DB::statement("
            CREATE VIEW vista_reporte_cancelaciones AS
            SELECT 
                c.fecha AS fecha_cita,
                c.estado,
                c.motivo_cancelacion,
                c.es_cupo_recuperado,
                e.id AS especialidad_id,
                e.nombre AS especialidad_nombre,
                m.id AS medico_id,
                u_m.name AS medico_nombre,
                COUNT(c.id) AS total_citas
            FROM citas c
            INNER JOIN medicos m ON c.medico_id = m.id
            INNER JOIN users u_m ON m.user_id = u_m.id
            INNER JOIN especialidades e ON m.especialidad_id = e.id
            WHERE c.estado IN ('cancelada', 'reprogramada', 'inasistencia', 'postergada')
            GROUP BY c.fecha, c.estado, c.motivo_cancelacion, c.es_cupo_recuperado, e.id, e.nombre, m.id, u_m.name
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS vista_reporte_cancelaciones');
        DB::statement('DROP VIEW IF EXISTS vista_reporte_ocupacion');
        DB::statement('DROP VIEW IF EXISTS vista_reporte_citas');
        Schema::dropIfExists('reportes_ia');
    }
};
