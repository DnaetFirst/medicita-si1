<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS vista_reporte_medicos');
        DB::statement('
            CREATE VIEW vista_reporte_medicos AS
            SELECT 
                m.id AS medico_id,
                u.name AS medico_nombre,
                u.email AS medico_email,
                e.id AS especialidad_id,
                e.nombre AS especialidad_nombre,
                e.codigo AS especialidad_codigo,
                m.nro_registro_medico,
                m.telefono_contacto,
                m.estado AS medico_estado,
                m.biografia
            FROM medicos m
            INNER JOIN users u ON m.user_id = u.id
            INNER JOIN especialidades e ON m.especialidad_id = e.id
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS vista_reporte_medicos');
    }
};
