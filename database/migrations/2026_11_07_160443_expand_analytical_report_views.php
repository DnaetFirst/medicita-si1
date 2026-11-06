<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE VIEW vista_reporte_movimientos AS SELECT h.id AS movimiento_id, h.cita_id, h.operacion, h.estado_anterior, h.estado_nuevo, h.created_at AS fecha_evento, h.fecha_anterior, h.fecha_nueva, m.id AS medico_id, u.name AS medico_nombre, e.id AS especialidad_id, e.nombre AS especialidad_nombre FROM historial_citas h JOIN medicos m ON m.id = h.medico_nuevo_id JOIN users u ON u.id = m.user_id JOIN especialidades e ON e.id = m.especialidad_id');
        DB::statement("CREATE VIEW vista_reporte_pacientes AS SELECT id AS paciente_id, (nombres || ' ' || apellidos) AS paciente_nombre, activo, created_at AS fecha_registro FROM pacientes");
        DB::statement('CREATE VIEW vista_reporte_espera AS SELECT l.id AS lista_id, l.paciente_id, l.especialidad_id, e.nombre AS especialidad_nombre, l.medico_id, l.fecha_inscripcion, l.fecha_desde, l.fecha_hasta, l.estado FROM listas_espera l JOIN especialidades e ON e.id = l.especialidad_id');
        DB::statement('CREATE VIEW vista_reporte_ofertas AS SELECT o.id AS oferta_id, o.lista_espera_id AS lista_id, l.paciente_id, o.estado, o.fecha_oferta, o.fecha_vencimiento, o.respondida_at, s.fecha AS fecha_turno, s.medico_id, e.id AS especialidad_id, e.nombre AS especialidad_nombre FROM ofertas_cupo o JOIN listas_espera l ON l.id = o.lista_espera_id JOIN slots s ON s.id = o.slot_id JOIN medicos m ON m.id = s.medico_id JOIN especialidades e ON e.id = m.especialidad_id');
        DB::statement('CREATE VIEW vista_reporte_laboratorio AS SELECT o.id AS orden_id, o.paciente_id, o.medico_id, m.especialidad_id, e.nombre AS especialidad_nombre, o.estado, o.created_at AS fecha_emision FROM ordenes_laboratorio o JOIN medicos m ON m.id = o.medico_id JOIN especialidades e ON e.id = m.especialidad_id');
    }

    public function down(): void
    {
        foreach (['vista_reporte_movimientos', 'vista_reporte_pacientes', 'vista_reporte_espera', 'vista_reporte_ofertas', 'vista_reporte_laboratorio'] as $vista) {
            DB::statement('DROP VIEW IF EXISTS '.$vista);
        }
    }
};
