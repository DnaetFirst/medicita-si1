<?php

namespace App\Services;

use App\Models\P6\ReporteIa;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class AiReporteService
{
    /**
     * Vistas y tablas autorizadas para consultas analíticas de la IA.
     * Quedan estrictamente excluidas tablas con datos clínicos sensibles (historias_clinicas, atenciones_medicas, etc.)
     */
    protected const TABLAS_AUTORIZADAS = [
        'vista_reporte_citas',
        'vista_reporte_ocupacion',
        'vista_reporte_cancelaciones',
        'citas',
        'slots',
        'especialidades',
        'medicos',
        'pacientes',
    ];

    /**
     * Tablas prohibidas que contienen información clínica confidencial o credenciales.
     */
    protected const TABLAS_PROHIBIDAS = [
        'historias_clinicas',
        'atenciones_medicas',
        'derivaciones',
        'resultados_laboratorio',
        'detalles_resultado',
        'users',
        'bitacoras',
        'password_reset_tokens',
        'sessions',
    ];

    /**
     * Ejecuta una consulta analítica en lenguaje natural con validación AST y auditoría.
     *
     * @return array<string, mixed>
     */
    public function ejecutarConsulta(string $pregunta, int $userId, ?string $sqlManual = null): array
    {
        $inicio = microtime(true);
        $pregunta = trim($pregunta);

        // 1. Generar SQL desde el lenguaje natural (o usar el provisto)
        $sql = $sqlManual ? trim($sqlManual) : $this->generarSqlDesdePregunta($pregunta);

        // 2. Validación estructural de seguridad (AST / Regex)
        $validacion = $this->validarSql($sql);

        if (! $validacion['es_valido']) {
            $tiempoMs = (int) round((microtime(true) - $inicio) * 1000);

            // Auditar intento fallido / bloqueado
            ReporteIa::create([
                'user_id' => $userId,
                'pregunta_usuario' => $pregunta,
                'sql_generado' => $sql,
                'es_valido' => false,
                'motivo_rechazo' => $validacion['motivo'],
                'filas_retornadas' => 0,
                'tiempo_ms' => $tiempoMs,
            ]);

            return [
                'ok' => false,
                'mensaje' => $validacion['motivo'],
                'sugerencia' => 'Por seguridad y privacidad clínica, la consulta no pudo ser ejecutada. Puedes utilizar los filtros tradicionales del panel.',
                'sql' => $sql,
                'columnas' => [],
                'filas' => [],
                'total' => 0,
                'tiempo_ms' => $tiempoMs,
            ];
        }

        // 3. Ejecución segura con límite forzado
        $sqlLimpio = $this->asegurarLimite($sql, 100);

        try {
            $resultados = DB::select($sqlLimpio);
            $tiempoMs = (int) round((microtime(true) - $inicio) * 1000);
            $filas = array_map(fn ($r) => (array) $r, $resultados);
            $columnas = ! empty($filas) ? array_keys($filas[0]) : [];
            $totalFilas = count($filas);

            // Auditar consulta exitosa
            ReporteIa::create([
                'user_id' => $userId,
                'pregunta_usuario' => $pregunta,
                'sql_generado' => $sqlLimpio,
                'es_valido' => true,
                'motivo_rechazo' => null,
                'filas_retornadas' => $totalFilas,
                'tiempo_ms' => $tiempoMs,
            ]);

            return [
                'ok' => true,
                'mensaje' => 'Consulta ejecutada con éxito.',
                'sql' => $sqlLimpio,
                'columnas' => $columnas,
                'filas' => $filas,
                'total' => $totalFilas,
                'tiempo_ms' => $tiempoMs,
                'recomendacion_grafico' => $this->recomendarGrafico($columnas, $filas),
            ];
        } catch (Throwable $e) {
            $tiempoMs = (int) round((microtime(true) - $inicio) * 1000);

            ReporteIa::create([
                'user_id' => $userId,
                'pregunta_usuario' => $pregunta,
                'sql_generado' => $sqlLimpio,
                'es_valido' => false,
                'motivo_rechazo' => 'Error de ejecución SQL: '.$e->getMessage(),
                'filas_retornadas' => 0,
                'tiempo_ms' => $tiempoMs,
            ]);

            return [
                'ok' => false,
                'mensaje' => 'No fue posible ejecutar la consulta en la base de datos.',
                'sugerencia' => 'Verifica la sintaxis o consulta mediante los filtros tradicionales.',
                'sql' => $sqlLimpio,
                'columnas' => [],
                'filas' => [],
                'total' => 0,
                'tiempo_ms' => $tiempoMs,
            ];
        }
    }

    /**
     * Validador estructural estricto de SQL (AST / Regex).
     * Solo permite sentencias SELECT sobre vistas analíticas autorizadas.
     *
     * @return array{es_valido: bool, motivo: ?string}
     */
    public function validarSql(string $sql): array
    {
        $sql = trim($sql);

        if (empty($sql)) {
            return ['es_valido' => false, 'motivo' => 'La consulta SQL está vacía.'];
        }

        // 1. Debe comenzar estrictamente con SELECT (o WITH para CTE de solo lectura)
        if (! preg_match('/^(SELECT|WITH)\s+/i', $sql)) {
            return ['es_valido' => false, 'motivo' => 'Solo se permiten sentencias de lectura (SELECT).'];
        }

        // 2. Rechazar múltiples sentencias (punto y coma seguido de caracteres no vacíos)
        if (preg_match('/;(\s*\S+)/', $sql)) {
            return ['es_valido' => false, 'motivo' => 'No se permite la ejecución de múltiples sentencias.'];
        }

        // 3. Rechazar comentarios SQL
        if (preg_match('/(--|\/\*|\*\/|#)/', $sql)) {
            return ['es_valido' => false, 'motivo' => 'No se permiten comentarios en las consultas SQL.'];
        }

        // 4. Bloquear palabras clave destructivas, de modificación o DDL
        $patronDdlDml = '/\b(INSERT|UPDATE|DELETE|DROP|ALTER|TRUNCATE|CREATE|RENAME|REPLACE|EXEC|EXECUTE|CALL|GRANT|REVOKE|LOCK|UNLOCK)\b/i';
        if (preg_match($patronDdlDml, $sql, $matches)) {
            return ['es_valido' => false, 'motivo' => 'Comando no permitido: '.strtoupper($matches[1])];
        }

        // 5. Bloquear funciones peligrosas y de introspección del motor
        $patronPeligroso = '/\b(information_schema|pg_|sqlite_|benchmark|sleep|load_file|into\s+outfile|dumpfile|current_user|session_user)\b/i';
        if (preg_match($patronPeligroso, $sql, $matches)) {
            return ['es_valido' => false, 'motivo' => 'Introspección o función de sistema bloqueada: '.$matches[1]];
        }

        // 6. Verificar que NO consulte tablas con datos clínicos sensibles
        foreach (self::TABLAS_PROHIBIDAS as $tablaProhibida) {
            if (preg_match('/\b'.preg_quote($tablaProhibida, '/').'\b/i', $sql)) {
                return [
                    'es_valido' => false,
                    'motivo' => 'Acceso denegado: Por normativa de confidencialidad clínica, no se permite consultar la tabla '.$tablaProhibida,
                ];
            }
        }

        // 7. Verificar que consulte al menos una tabla/vista autorizada
        $tieneAutorizada = false;
        foreach (self::TABLAS_AUTORIZADAS as $tablaAutorizada) {
            if (preg_match('/\b'.preg_quote($tablaAutorizada, '/').'\b/i', $sql)) {
                $tieneAutorizada = true;
                break;
            }
        }

        if (! $tieneAutorizada) {
            return [
                'es_valido' => false,
                'motivo' => 'La consulta debe realizarse sobre las vistas analíticas autorizadas (vista_reporte_citas, vista_reporte_ocupacion, vista_reporte_cancelaciones).',
            ];
        }

        return ['es_valido' => true, 'motivo' => null];
    }

    /**
     * Asegura que la consulta SQL tenga una cláusula LIMIT no superior al máximo establecido.
     */
    public function asegurarLimite(string $sql, int $max = 100): string
    {
        $sql = rtrim($sql, '; ');

        if (preg_match('/\bLIMIT\s+(\d+)\b/i', $sql, $matches)) {
            $limiteActual = (int) $matches[1];
            if ($limiteActual > $max) {
                return preg_replace('/\bLIMIT\s+\d+\b/i', "LIMIT {$max}", $sql);
            }

            return $sql;
        }

        return "{$sql} LIMIT {$max}";
    }

    /**
     * Motor de traducción semántica de lenguaje natural a SQL analítico.
     */
    public function generarSqlDesdePregunta(string $pregunta): string
    {
        $p = mb_strtolower(trim($pregunta));

        // 1. Cancelaciones
        if (str_contains($p, 'cancelad') || str_contains($p, 'cancelaron') || str_contains($p, 'cancelaci')) {
            if (str_contains($p, 'motivo') || str_contains($p, 'por qué') || str_contains($p, 'razon')) {
                return "SELECT motivo_cancelacion, COUNT(*) AS total_canceladas FROM vista_reporte_citas WHERE estado = 'cancelada' AND motivo_cancelacion IS NOT NULL GROUP BY motivo_cancelacion ORDER BY total_canceladas DESC LIMIT 20";
            }

            if (str_contains($p, 'especialidad')) {
                return "SELECT especialidad_nombre, COUNT(*) AS total_canceladas FROM vista_reporte_citas WHERE estado = 'cancelada' GROUP BY especialidad_nombre ORDER BY total_canceladas DESC LIMIT 20";
            }

            if (str_contains($p, 'medico') || str_contains($p, 'doctor')) {
                return "SELECT medico_nombre, especialidad_nombre, COUNT(*) AS total_canceladas FROM vista_reporte_citas WHERE estado = 'cancelada' GROUP BY medico_nombre, especialidad_nombre ORDER BY total_canceladas DESC LIMIT 20";
            }

            return "SELECT codigo_ficha, fecha_cita, hora_cita, medico_nombre, especialidad_nombre, paciente_nombre, motivo_cancelacion FROM vista_reporte_citas WHERE estado = 'cancelada' ORDER BY fecha_cita DESC LIMIT 50";
        }

        // 2. Inasistencias (No-shows)
        if (str_contains($p, 'inasistencia') || str_contains($p, 'no asist') || str_contains($p, 'faltaron') || str_contains($p, 'no show')) {
            if (str_contains($p, 'especialidad')) {
                return "SELECT especialidad_nombre, COUNT(*) AS total_inasistencias FROM vista_reporte_citas WHERE estado = 'inasistencia' GROUP BY especialidad_nombre ORDER BY total_inasistencias DESC LIMIT 20";
            }

            return "SELECT codigo_ficha, fecha_cita, hora_cita, medico_nombre, especialidad_nombre, paciente_nombre FROM vista_reporte_citas WHERE estado = 'inasistencia' ORDER BY fecha_cita DESC LIMIT 50";
        }

        // 3. Cupos recuperados (Lista de espera - CU18)
        if (str_contains($p, 'recuperad') || str_contains($p, 'lista de espera') || str_contains($p, 'espera') || str_contains($p, 'cupo')) {
            return "SELECT codigo_ficha, fecha_cita, hora_cita, especialidad_nombre, medico_nombre, paciente_nombre, estado FROM vista_reporte_citas WHERE es_cupo_recuperado = 1 OR es_cupo_recuperado = true ORDER BY fecha_cita DESC LIMIT 50";
        }

        // 4. Ocupación y turnos
        if (str_contains($p, 'ocupaci') || str_contains($p, 'disponib') || str_contains($p, 'slot') || str_contains($p, 'turno')) {
            return "SELECT fecha, medico_nombre, especialidad_nombre, total_slots, slots_reservados, slots_disponibles, slots_bloqueados FROM vista_reporte_ocupacion ORDER BY fecha DESC LIMIT 50";
        }

        // 5. Médicos con más pacientes o citas
        if (str_contains($p, 'medico') || str_contains($p, 'doctor') || str_contains($p, 'mas citas') || str_contains($p, 'más atendieron')) {
            return "SELECT medico_nombre, especialidad_nombre, COUNT(*) AS total_citas FROM vista_reporte_citas GROUP BY medico_nombre, especialidad_nombre ORDER BY total_citas DESC LIMIT 15";
        }

        // 6. Demanda por especialidad
        if (str_contains($p, 'especialidad') || str_contains($p, 'demanda') || str_contains($p, 'solicitad')) {
            return "SELECT especialidad_nombre, COUNT(*) AS total_citas FROM vista_reporte_citas GROUP BY especialidad_nombre ORDER BY total_citas DESC LIMIT 20";
        }

        // 7. Atendidas / Completadas
        if (str_contains($p, 'atendid') || str_contains($p, 'completad') || str_contains($p, 'consultas')) {
            return "SELECT codigo_ficha, fecha_cita, hora_cita, medico_nombre, especialidad_nombre, paciente_nombre FROM vista_reporte_citas WHERE estado IN ('atendida', 'completada', 'en_atencion') ORDER BY fecha_cita DESC LIMIT 50";
        }

        // Consulta general por defecto: últimas citas
        return "SELECT codigo_ficha, fecha_cita, hora_cita, estado, especialidad_nombre, medico_nombre, paciente_nombre FROM vista_reporte_citas ORDER BY fecha_cita DESC LIMIT 50";
    }

    /**
     * Determina el tipo de gráfico sugerido según los datos analíticos devueltos.
     *
     * @param  array<string>  $columnas
     * @param  array<array<string, mixed>>  $filas
     */
    protected function recomendarGrafico(array $columnas, array $filas): string
    {
        if (empty($filas) || count($filas) > 30) {
            return 'tabla';
        }

        // Si contiene agrupaciones y conteos (ej. total, conteo, total_citas, total_canceladas)
        foreach ($columnas as $col) {
            if (in_array(strtolower($col), ['total', 'total_citas', 'total_canceladas', 'total_inasistencias', 'slots_reservados'])) {
                return count($filas) <= 6 ? 'torta' : 'barras';
            }
        }

        return 'tabla';
    }
}
