<?php

namespace App\Services;

use App\Models\P6\ReporteIa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
     * Mapeo de términos de columnas para extracción semántica y filtrado dinámico.
     */
    protected const MAPA_COLUMNAS_MEDICOS = [
        'medico_nombre' => ['nombre', 'medico', 'médico', 'doctor', 'profesional'],
        'especialidad_nombre' => ['especialidad', 'especialidades', 'rama', 'área', 'area'],
        'nro_registro_medico' => ['matricula', 'matrícula', 'registro', 'licencia', 'nro_registro', 'colegiado'],
        'telefono_contacto' => ['telefono', 'teléfono', 'celular', 'contacto', 'fono'],
        'medico_email' => ['email', 'correo', 'mail'],
        'medico_estado' => ['estado', 'activo', 'condicion'],
        'biografia' => ['biografia', 'biografía', 'perfil', 'descripcion', 'descripción'],
        'medico_id' => ['id', 'codigo'],
    ];

    protected const MAPA_COLUMNAS_CITAS = [
        'codigo_ficha' => ['ficha', 'codigo', 'código', 'numero', 'nro'],
        'fecha_cita' => ['fecha', 'dia', 'día'],
        'hora_cita' => ['hora', 'horario'],
        'estado' => ['estado', 'situacion', 'condicion'],
        'medico_nombre' => ['medico', 'médico', 'doctor'],
        'especialidad_nombre' => ['especialidad', 'especialidades'],
        'paciente_nombre' => ['paciente', 'persona', 'usuario'],
        'paciente_ci' => ['ci', 'cedula', 'cédula', 'documento'],
        'motivo_cancelacion' => ['motivo', 'razon', 'razón', 'causa', 'motivo_cancelacion'],
        'es_cupo_recuperado' => ['recuperado', 'cupo', 'espera'],
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
     * Motor de generación SQL: invoca el LLM de Google Gemini si existe API Key
     * o recurre al motor semántico adaptativo basado en esquemas.
     */
    public function generarSqlDesdePregunta(string $pregunta): string
    {
        // 1. Intento con Google Gemini API si la clave está configurada
        $sqlGemini = $this->generarSqlConGemini($pregunta);
        if ($sqlGemini !== null) {
            $val = $this->validarSql($sqlGemini);
            if ($val['es_valido']) {
                return $sqlGemini;
            }
        }

        // 2. Motor semántico adaptativo dinámico en tiempo real
        return $this->generarSqlSemanticoDinamico($pregunta);
    }

    /**
     * Invoca la API de Google Gemini para sintetizar consultas SQL analíticas de solo lectura.
     */
    public function generarSqlConGemini(string $pregunta): ?string
    {
        $apiKey = config('services.gemini.key') ?: env('GEMINI_API_KEY');
        if (empty($apiKey)) {
            return null;
        }

        $model = config('services.gemini.model', 'gemini-1.5-flash');

        $systemPrompt = <<<'PROMPT'
Eres el motor de IA analítico para el sistema médico MediCita Pro.
Convierte la pregunta en lenguaje natural del usuario a una ÚNICA consulta SQL de solo lectura compatible con PostgreSQL y SQLite.

VISTAS ANALÍTICAS Y CAMPOS DISPONIBLES:
1. vista_reporte_medicos: (medico_id, medico_nombre, medico_email, especialidad_id, especialidad_nombre, especialidad_codigo, nro_registro_medico, telefono_contacto, medico_estado, biografia)
2. vista_reporte_citas: (cita_id, codigo_ficha, fecha_cita, hora_cita, estado, motivo_cancelacion, cancelado_por_user_id, es_cupo_recuperado, hora_confirmacion, hora_llegada, hora_inicio_atencion, hora_fin_atencion, medico_id, medico_nombre, especialidad_id, especialidad_nombre, paciente_id, paciente_nombre, paciente_ci, created_at)
3. vista_reporte_ocupacion: (fecha, medico_id, medico_nombre, especialidad_id, especialidad_nombre, total_slots, slots_reservados, slots_disponibles, slots_bloqueados)
4. vista_reporte_cancelaciones: (fecha_cita, estado, motivo_cancelacion, es_cupo_recuperado, especialidad_id, especialidad_nombre, medico_id, medico_nombre, total_citas)

REGLAS DE SEGURIDAD ESTRICTAS:
- Retorna ÚNICAMENTE la sentencia SQL limpia. Sin explicaciones ni texto adicional. Sin bloques de markdown (no uses ```sql).
- Si el usuario pide "todos los médicos registrados" o información de médicos con especialidades, consulta SIEMPRE vista_reporte_medicos.
- Si el usuario solicita excluir columnas ("sin teléfono", "sin matrícula", "no me muestres X"), OMITE esas columnas del SELECT.
- Si el usuario pide columnas específicas ("solo nombre y especialidad"), incluye ÚNICAMENTE esas columnas en el SELECT.
- NUNCA consultes la tabla 'users' directamente, ni historias clínicas confidenciales.
- Solo sentencias SELECT.
PROMPT;

        try {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
            $response = Http::timeout(6)->post($url, [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => $systemPrompt."\n\nPregunta del usuario: ".$pregunta],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.0,
                    'maxOutputTokens' => 300,
                ],
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $texto = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
                if ($texto) {
                    $sql = preg_replace('/^```(?:sql)?\s*/i', '', trim($texto));
                    $sql = preg_replace('/\s*```$/', '', $sql);
                    $sql = trim($sql);
                    if (! empty($sql)) {
                        return $sql;
                    }
                }
            }
        } catch (Throwable $e) {
            Log::warning('Error en llamada a Gemini API: '.$e->getMessage());
        }

        return null;
    }

    /**
     * Motor semántico adaptativo: interpreta dinámicamente entidades, proyecciones
     * de columnas (inclusiones 'solo...' y exclusiones 'sin...'), filtros y agrupaciones.
     */
    public function generarSqlSemanticoDinamico(string $pregunta): string
    {
        $p = mb_strtolower(trim($pregunta));

        // 1. Detectar si el objetivo principal son los MÉDICOS REGISTRADOS
        $esConsultaMedicosRegistrados = (
            str_contains($p, 'medicos registrados') ||
            str_contains($p, 'doctores registrados') ||
            str_contains($p, 'todos los medicos') ||
            str_contains($p, 'todos los doctores') ||
            str_contains($p, 'lista de medicos') ||
            str_contains($p, 'lista de doctores') ||
            str_contains($p, 'plantel medico') ||
            str_contains($p, 'cuerpo medico') ||
            (
                (str_contains($p, 'medico') || str_contains($p, 'doctor')) &&
                ! str_contains($p, 'cita') &&
                ! str_contains($p, 'cancel') &&
                ! str_contains($p, 'slot') &&
                ! str_contains($p, 'turno') &&
                ! str_contains($p, 'ocupaci') &&
                ! str_contains($p, 'espera') &&
                ! str_contains($p, 'recuperad') &&
                ! str_contains($p, 'atendid') &&
                ! str_contains($p, 'paciente')
            )
        );

        if ($esConsultaMedicosRegistrados) {
            $columnasBase = ['medico_nombre', 'especialidad_nombre', 'nro_registro_medico', 'telefono_contacto', 'medico_email'];
            $columnas = $this->filtrarColumnasDinamicas($p, $columnasBase, self::MAPA_COLUMNAS_MEDICOS, 'medico_nombre');
            $colsSql = implode(', ', $columnas);

            return "SELECT {$colsSql} FROM vista_reporte_medicos ORDER BY medico_nombre ASC LIMIT 50";
        }

        // 2. Cancelaciones
        if (str_contains($p, 'cancelad') || str_contains($p, 'cancelaron') || str_contains($p, 'cancelaci')) {
            $esSinMotivo = (bool) preg_match('/(?:sin|no\s+(?:me\s+)?muestres?|ocultar|excepto)\s+(?:el\s+)?motivo/iu', $p);
            $quiereAgruparPorMotivo = ! $esSinMotivo && (
                str_contains($p, 'por qué') ||
                str_contains($p, 'por que') ||
                str_contains($p, 'razon') ||
                str_contains($p, 'razón') ||
                (str_contains($p, 'motivo') && (str_contains($p, 'por') || str_contains($p, 'cuant') || str_contains($p, 'cual') || str_contains($p, 'cuál')))
            );

            if ($quiereAgruparPorMotivo) {
                return "SELECT motivo_cancelacion, COUNT(*) AS total_canceladas FROM vista_reporte_citas WHERE estado = 'cancelada' AND motivo_cancelacion IS NOT NULL GROUP BY motivo_cancelacion ORDER BY total_canceladas DESC LIMIT 20";
            }

            if (str_contains($p, 'especialidad') && (str_contains($p, 'por') || str_contains($p, 'cuant'))) {
                return "SELECT especialidad_nombre, COUNT(*) AS total_canceladas FROM vista_reporte_citas WHERE estado = 'cancelada' GROUP BY especialidad_nombre ORDER BY total_canceladas DESC LIMIT 20";
            }

            if ((str_contains($p, 'medico') || str_contains($p, 'doctor')) && (str_contains($p, 'por') || str_contains($p, 'cuant'))) {
                return "SELECT medico_nombre, especialidad_nombre, COUNT(*) AS total_canceladas FROM vista_reporte_citas WHERE estado = 'cancelada' GROUP BY medico_nombre, especialidad_nombre ORDER BY total_canceladas DESC LIMIT 20";
            }

            $columnasBase = ['codigo_ficha', 'fecha_cita', 'hora_cita', 'medico_nombre', 'especialidad_nombre', 'paciente_nombre', 'motivo_cancelacion'];
            $columnas = $this->filtrarColumnasDinamicas($p, $columnasBase, self::MAPA_COLUMNAS_CITAS, 'codigo_ficha');
            $colsSql = implode(', ', $columnas);

            return "SELECT {$colsSql} FROM vista_reporte_citas WHERE estado = 'cancelada' ORDER BY fecha_cita DESC LIMIT 50";
        }

        // 3. Inasistencias (No-shows)
        if (str_contains($p, 'inasistencia') || str_contains($p, 'no asist') || str_contains($p, 'faltaron') || str_contains($p, 'no show')) {
            if (str_contains($p, 'especialidad')) {
                return "SELECT especialidad_nombre, COUNT(*) AS total_inasistencias FROM vista_reporte_citas WHERE estado = 'inasistencia' GROUP BY especialidad_nombre ORDER BY total_inasistencias DESC LIMIT 20";
            }

            $columnasBase = ['codigo_ficha', 'fecha_cita', 'hora_cita', 'medico_nombre', 'especialidad_nombre', 'paciente_nombre'];
            $columnas = $this->filtrarColumnasDinamicas($p, $columnasBase, self::MAPA_COLUMNAS_CITAS, 'codigo_ficha');
            $colsSql = implode(', ', $columnas);

            return "SELECT {$colsSql} FROM vista_reporte_citas WHERE estado = 'inasistencia' ORDER BY fecha_cita DESC LIMIT 50";
        }

        // 4. Cupos recuperados (Lista de espera - CU18)
        if (str_contains($p, 'recuperad') || str_contains($p, 'lista de espera') || str_contains($p, 'espera') || str_contains($p, 'cupo')) {
            $columnasBase = ['codigo_ficha', 'fecha_cita', 'hora_cita', 'especialidad_nombre', 'medico_nombre', 'paciente_nombre', 'estado'];
            $columnas = $this->filtrarColumnasDinamicas($p, $columnasBase, self::MAPA_COLUMNAS_CITAS, 'codigo_ficha');
            $colsSql = implode(', ', $columnas);

            return "SELECT {$colsSql} FROM vista_reporte_citas WHERE es_cupo_recuperado = 1 OR es_cupo_recuperado = true ORDER BY fecha_cita DESC LIMIT 50";
        }

        // 5. Ocupación y turnos
        if (str_contains($p, 'ocupaci') || str_contains($p, 'disponib') || str_contains($p, 'slot') || str_contains($p, 'turno')) {
            return 'SELECT fecha, medico_nombre, especialidad_nombre, total_slots, slots_reservados, slots_disponibles, slots_bloqueados FROM vista_reporte_ocupacion ORDER BY fecha DESC LIMIT 50';
        }

        // 6. Médicos con más pacientes o citas
        if (str_contains($p, 'mas citas') || str_contains($p, 'más citas') || str_contains($p, 'mas atendieron') || str_contains($p, 'mayor atencion') || str_contains($p, 'mayor cantidad')) {
            return 'SELECT medico_nombre, especialidad_nombre, COUNT(*) AS total_citas FROM vista_reporte_citas GROUP BY medico_nombre, especialidad_nombre ORDER BY total_citas DESC LIMIT 15';
        }

        // 7. Demanda por especialidad
        if (str_contains($p, 'demanda') || (str_contains($p, 'especialidad') && str_contains($p, 'solicitad'))) {
            return 'SELECT especialidad_nombre, COUNT(*) AS total_citas FROM vista_reporte_citas GROUP BY especialidad_nombre ORDER BY total_citas DESC LIMIT 20';
        }

        // 8. Atendidas / Completadas
        if (str_contains($p, 'atendid') || str_contains($p, 'completad') || str_contains($p, 'consultas')) {
            $columnasBase = ['codigo_ficha', 'fecha_cita', 'hora_cita', 'medico_nombre', 'especialidad_nombre', 'paciente_nombre'];
            $columnas = $this->filtrarColumnasDinamicas($p, $columnasBase, self::MAPA_COLUMNAS_CITAS, 'codigo_ficha');
            $colsSql = implode(', ', $columnas);

            return "SELECT {$colsSql} FROM vista_reporte_citas WHERE estado IN ('atendida', 'completada', 'en_atencion') ORDER BY fecha_cita DESC LIMIT 50";
        }

        // 9. Consulta general de citas con filtrado de columnas adaptativo
        $columnasBase = ['codigo_ficha', 'fecha_cita', 'hora_cita', 'estado', 'especialidad_nombre', 'medico_nombre', 'paciente_nombre'];
        $columnas = $this->filtrarColumnasDinamicas($p, $columnasBase, self::MAPA_COLUMNAS_CITAS, 'codigo_ficha');
        $colsSql = implode(', ', $columnas);

        return "SELECT {$colsSql} FROM vista_reporte_citas ORDER BY fecha_cita DESC LIMIT 50";
    }

    /**
     * Aplica reglas de inclusión ('solo...') y exclusión ('sin...', 'no mostrar...') sobre el conjunto de columnas.
     *
     * @param  array<string>  $columnasBase
     * @param  array<string, array<string>>  $mapaTerminos
     * @return array<string>
     */
    protected function filtrarColumnasDinamicas(string $pregunta, array $columnasBase, array $mapaTerminos, string $columnaFallback): array
    {
        // 1. Verificar si el usuario solicita estrictamente 'solo' ciertas columnas
        if (preg_match('/(?:solo|solamente|únicamente|unicamente)\s+(?:el\s+|la\s+|los\s+|las\s+)?([^,.;]+)/iu', $pregunta, $mSolo)) {
            $textoInclusion = mb_strtolower($mSolo[1]);
            $columnasSeleccionadas = [];

            foreach ($mapaTerminos as $columnaSql => $terminos) {
                foreach ($terminos as $termino) {
                    if (preg_match('/\b'.preg_quote($termino, '/').'\b/iu', $textoInclusion)) {
                        $columnasSeleccionadas[] = $columnaSql;
                        break;
                    }
                }
            }

            if (! empty($columnasSeleccionadas)) {
                return array_values(array_unique($columnasSeleccionadas));
            }
        }

        // 2. Verificar si el usuario solicita excluir columnas ('sin X', 'no mostrar X', etc.)
        $patronExclusion = '/(?:sin|no\s+(?:me\s+)?muestres?|no\s+mostrar|no\s+incluir|ocultar|excepto|quitar|menos)\s+(?:el\s+|la\s+|los\s+|las\s+)?([^,.;]+)/iu';
        $columnasExcluidas = [];

        if (preg_match_all($patronExclusion, $pregunta, $coincidencias)) {
            $textoExclusiones = mb_strtolower(implode(' ', $coincidencias[1]));

            foreach ($mapaTerminos as $columnaSql => $terminos) {
                foreach ($terminos as $termino) {
                    if (preg_match('/\b'.preg_quote($termino, '/').'\b/iu', $textoExclusiones)) {
                        $columnasExcluidas[] = $columnaSql;
                        break;
                    }
                }
            }
        }

        $resultado = array_values(array_diff($columnasBase, $columnasExcluidas));

        return ! empty($resultado) ? $resultado : [$columnaFallback];
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
