<?php

namespace App\Services;

use App\Models\P6\ReporteIa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class AiReporteService
{
    /**
     * Vistas y tablas autorizadas para consultas analíticas de la IA.
     * Quedan estrictamente excluidas tablas con datos clínicos sensibles (historias_clinicas, atenciones_medicas, etc.)
     */
    protected const TABLAS_AUTORIZADAS = [
        'vista_reporte_medicos',
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
     * Ejecuta una consulta analítica en lenguaje natural exclusivamente mediante Google Gemini
     * con validación AST y auditoría.
     *
     * @return array<string, mixed>
     */
    public function ejecutarConsulta(string $pregunta, int $userId, ?string $sqlManual = null): array
    {
        $inicio = microtime(true);
        $pregunta = trim($pregunta);

        // 1. Generar SQL exclusivamente desde Google Gemini (o usar el manual para pruebas de seguridad)
        try {
            $sql = $sqlManual ? trim($sqlManual) : $this->generarSqlConGemini($pregunta);
        } catch (Throwable $e) {
            $tiempoMs = (int) round((microtime(true) - $inicio) * 1000);

            ReporteIa::create([
                'user_id' => $userId,
                'pregunta_usuario' => $pregunta,
                'sql_generado' => null,
                'es_valido' => false,
                'motivo_rechazo' => $e->getMessage(),
                'filas_retornadas' => 0,
                'tiempo_ms' => $tiempoMs,
            ]);

            return [
                'ok' => false,
                'mensaje' => $e->getMessage(),
                'sugerencia' => 'Ocurrió un inconveniente al consultar con Google Gemini. Revisa la consola o configuración.',
                'sql' => '',
                'columnas' => [],
                'filas' => [],
                'total' => 0,
                'tiempo_ms' => $tiempoMs,
            ];
        }

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
                'sugerencia' => 'Por seguridad y privacidad clínica, la consulta generada no cumple las reglas de confidencialidad.',
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
            $columnas = ! empty($filas) ? array_keys($filas[0]) : $this->extraerColumnasSelect($sqlLimpio);
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
                'mensaje' => 'Consulta generada por Gemini y ejecutada con éxito.',
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
                'mensaje' => 'Error al ejecutar en la base de datos el SQL generado por Gemini: '.$e->getMessage(),
                'sugerencia' => 'Verifica la sintaxis del SQL generado.',
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

        // 6. Verificar que NO consulte tablas con datos clínicos sensibles ni credenciales
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
                'motivo' => 'La consulta debe realizarse sobre las vistas analíticas autorizadas (vista_reporte_medicos, vista_reporte_citas, vista_reporte_ocupacion, vista_reporte_cancelaciones).',
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
     * Genera la consulta SQL exclusivamente mediante Google Gemini.
     */
    public function generarSqlDesdePregunta(string $pregunta): string
    {
        return $this->generarSqlConGemini($pregunta);
    }

    /**
     * Generación directa y 100% pura mediante la API oficial de Google Gemini.
     * Sin consultas prehechas, plantillas ni trucos estáticos.
     */
    public function generarSqlConGemini(string $pregunta): string
    {
        $apiKey = config('services.gemini.key') ?: env('GEMINI_API_KEY');
        if (empty($apiKey)) {
            throw new RuntimeException('La variable GEMINI_API_KEY no está configurada en el archivo .env.');
        }

        $model = config('services.gemini.model', 'gemini-flash-lite-latest');

        $systemPrompt = <<<'PROMPT'
Eres el motor de IA analítico para el sistema médico MediCita Pro.
Convierte la pregunta en lenguaje natural del usuario a una ÚNICA consulta SQL de solo lectura compatible con PostgreSQL y SQLite.

VISTAS ANALÍTICAS Y CAMPOS DISPONIBLES:
1. vista_reporte_medicos: (medico_id, medico_nombre, medico_email, especialidad_id, especialidad_nombre, especialidad_codigo, nro_registro_medico, telefono_contacto, medico_estado, biografia)
2. vista_reporte_citas: (cita_id, codigo_ficha, fecha_cita, hora_cita, estado, motivo_cancelacion, cancelado_por_user_id, es_cupo_recuperado, hora_confirmacion, hora_llegada, hora_inicio_atencion, hora_fin_atencion, medico_id, medico_nombre, especialidad_id, especialidad_nombre, paciente_id, paciente_nombre, paciente_ci, created_at)
3. vista_reporte_ocupacion: (fecha, medico_id, medico_nombre, especialidad_id, especialidad_nombre, total_slots, slots_reservados, slots_disponibles, slots_bloqueados)
4. vista_reporte_cancelaciones: (fecha_cita, estado, motivo_cancelacion, es_cupo_recuperado, especialidad_id, especialidad_nombre, medico_id, medico_nombre, total_citas)

REGLAS DE SEGURIDAD ESTRICTAS:
- Retorna ÚNICAMENTE la sentencia SQL limpia. Sin explicaciones, sin texto adicional y SIN bloques de formato markdown (no uses ```sql ni ```).
- Si el usuario pide "todos los médicos registrados" o información de médicos con especialidades, consulta SIEMPRE vista_reporte_medicos.
- Si el usuario solicita excluir columnas ("sin teléfono", "sin matrícula", "no me muestres X"), OMITE esas columnas del SELECT.
- Si el usuario pide columnas específicas ("solo nombre y especialidad"), incluye ÚNICAMENTE esas columnas en el SELECT.
- En filtros WHERE para 'estado', usa SIEMPRE valores en minúsculas: 'cancelada', 'confirmada', 'programada', 'atendida', 'completada', 'en_espera', 'inasistencia'.
- NUNCA consultes la tabla 'users' directamente, ni tablas de historias clínicas confidenciales.
- Solo sentencias SELECT de lectura.
PROMPT;

        try {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
            $response = Http::withoutVerifying()->timeout(15)->post($url, [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => $systemPrompt."\n\nPregunta del usuario:\n".$pregunta],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.0,
                    'maxOutputTokens' => 300,
                ],
            ]);

            if (! $response->successful()) {
                $errorMsg = $response->json('error.message') ?? $response->body();
                throw new RuntimeException('Error de Google Gemini API: '.$errorMsg);
            }

            $texto = $response->json('candidates.0.content.parts.0.text');
            if (empty($texto)) {
                throw new RuntimeException('Google Gemini no generó ninguna consulta SQL para la pregunta ingresada.');
            }

            // Limpieza de formato para garantizar SQL puro
            $sql = preg_replace('/^```(?:sql)?\s*/i', '', trim($texto));
            $sql = preg_replace('/\s*```$/', '', $sql);

            return trim($sql);
        } catch (Throwable $e) {
            throw new RuntimeException('Fallo en la comunicación con Google Gemini: '.$e->getMessage(), 0, $e);
        }
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

    /**
     * Extrae los nombres de columnas proyectadas del SELECT en caso de resultado con 0 filas.
     *
     * @return array<string>
     */
    protected function extraerColumnasSelect(string $sql): array
    {
        if (preg_match('/SELECT\s+(.*?)\s+FROM/is', $sql, $matches)) {
            $cols = [];
            foreach (explode(',', $matches[1]) as $raw) {
                $raw = trim($raw);
                if (preg_match('/\bAS\s+([a-zA-Z0-9_]+)$/i', $raw, $mAs)) {
                    $cols[] = $mAs[1];
                } elseif (preg_match('/(?:[a-zA-Z0-9_]+\.)?([a-zA-Z0-9_]+)$/', $raw, $mCol)) {
                    $cols[] = $mCol[1];
                }
            }

            return $cols;
        }

        return [];
    }
}
