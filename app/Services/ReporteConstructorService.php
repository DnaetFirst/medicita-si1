<?php

namespace App\Services;

use App\Models\P4\Cita;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Servicio para el constructor interactivo de reportes (CU17 Tipo 2).
 * Permite seleccionar columnas, agrupación y métricas de forma dinámica.
 */
class ReporteConstructorService
{
    /**
     * Columnas disponibles para seleccionar en la vista.
     *
     * @return array<string, string> clave => etiqueta
     */
    public static function columnasDisponibles(): array
    {
        return [
            'codigo_ficha' => 'Código de Ficha',
            'fecha' => 'Fecha de Cita',
            'hora' => 'Hora',
            'especialidad' => 'Especialidad',
            'medico' => 'Médico',
            'paciente' => 'Paciente',
            'ci' => 'CI Paciente',
            'estado' => 'Estado',
            'motivo_cancelacion' => 'Motivo Cancelación',
            'es_cupo_recuperado' => 'Cupo Recuperado',
            'fecha_registro' => 'Fecha de Registro',
        ];
    }

    /**
     * Agrupaciones disponibles.
     *
     * @return array<string, string> clave => etiqueta
     */
    public static function agrupacionesDisponibles(): array
    {
        return [
            'ninguna' => 'Sin agrupación (detalle individual)',
            'especialidad' => 'Por Especialidad',
            'medico' => 'Por Médico',
            'estado' => 'Por Estado de Cita',
            'mes' => 'Por Mes',
            'semana' => 'Por Semana',
        ];
    }

    /**
     * Métricas adicionales disponibles (aplican solo cuando hay agrupación).
     *
     * @return array<string, string>
     */
    public static function metricasDisponibles(): array
    {
        return [
            'total' => 'Total de citas',
            'tasa_asistencia' => 'Tasa de asistencia (%)',
            'cancelaciones' => 'Número de cancelaciones',
            'inasistencias' => 'Número de inasistencias',
        ];
    }

    /**
     * Ejecuta la construcción del reporte personalizado.
     *
     * @param  list<string>  $columnasSeleccionadas
     * @param  list<string>  $metricasSeleccionadas
     * @param  array<string, mixed>  $filtros
     * @return array{columnas: list<string>, filas: list<array<string, mixed>>, total: int, titulo: string, agrupacion_label: string}
     */
    public function construir(
        array $columnasSeleccionadas,
        string $agrupacion,
        array $metricasSeleccionadas,
        array $filtros = []
    ): array {
        $agrupaciones = self::agrupacionesDisponibles();
        $agrupacionLabel = $agrupaciones[$agrupacion] ?? 'Sin agrupación';

        if ($agrupacion === 'ninguna' || $agrupacion === '') {
            return $this->construirDetalle($columnasSeleccionadas, $filtros, $agrupacionLabel);
        }

        return $this->construirAgrupado($agrupacion, $metricasSeleccionadas, $filtros, $agrupacionLabel);
    }

    /**
     * Genera título descriptivo para el reporte.
     *
     * @param  list<string>  $metricas
     * @param  array<string, mixed>  $filtros
     */
    public function generarTitulo(string $agrupacion, array $metricas, array $filtros): string
    {
        $agrupaciones = self::agrupacionesDisponibles();
        $base = 'Reporte Personalizado';

        if ($agrupacion !== 'ninguna' && $agrupacion !== '') {
            $base = 'Reporte por '.($agrupaciones[$agrupacion] ?? $agrupacion);
        }

        $sufijos = [];
        if (! empty($filtros['fecha_inicio']) && ! empty($filtros['fecha_fin'])) {
            $sufijos[] = Carbon::parse($filtros['fecha_inicio'])->format('d/m/Y').' a '.Carbon::parse($filtros['fecha_fin'])->format('d/m/Y');
        } elseif (! empty($filtros['fecha_inicio'])) {
            $sufijos[] = 'desde '.Carbon::parse($filtros['fecha_inicio'])->format('d/m/Y');
        }

        return $base.(count($sufijos) > 0 ? ' — '.implode(', ', $sufijos) : '');
    }

    /**
     * Genera el nombre de archivo (sin extensión) a partir del título.
     */
    public function generarNombreArchivo(string $titulo): string
    {
        $nombre = mb_strtolower($titulo);
        $nombre = str_replace([' ', '—', '/', '(', ')'], ['_', '_', '_', '', ''], $nombre);
        $nombre = preg_replace('/[^a-z0-9_áéíóúñü]/u', '', $nombre) ?? 'reporte';
        $nombre = preg_replace('/_+/', '_', $nombre) ?? 'reporte';
        $nombre = trim($nombre, '_');
        $fecha = Carbon::now()->format('Ymd_His');

        return mb_substr($nombre, 0, 60)."_{$fecha}";
    }

    // ──────────────────────────────────────────────────────────────────
    // Construcción detallada (sin agrupación)
    // ──────────────────────────────────────────────────────────────────

    /** @param  array<string, mixed>  $filtros */
    private function construirDetalle(array $columnasSeleccionadas, array $filtros, string $agrupacionLabel): array
    {
        $columnasDef = self::columnasDisponibles();
        // Validar columnas seleccionadas
        $columnasValidas = array_filter($columnasSeleccionadas, fn ($c) => isset($columnasDef[$c]));
        if (empty($columnasValidas)) {
            $columnasValidas = ['fecha', 'especialidad', 'medico', 'estado'];
        }

        $query = Cita::query()
            ->with(['paciente', 'medico.user', 'medico.especialidad'])
            ->orderBy('fecha', 'desc')
            ->orderBy('hora', 'desc')
            ->limit(500);

        $this->aplicarFiltros($query, $filtros);
        $citas = $query->get();

        $etiquetas = [];
        foreach ($columnasValidas as $col) {
            $etiquetas[] = $columnasDef[$col] ?? $col;
        }

        $filas = [];
        foreach ($citas as $c) {
            $fila = [];
            foreach ($columnasValidas as $col) {
                $fila[$columnasDef[$col]] = $this->extraerValor($c, $col);
            }
            $filas[] = $fila;
        }

        return [
            'columnas' => $etiquetas,
            'filas' => $filas,
            'total' => count($filas),
            'agrupacion_label' => $agrupacionLabel,
        ];
    }

    // ──────────────────────────────────────────────────────────────────
    // Construcción agrupada
    // ──────────────────────────────────────────────────────────────────

    /**
     * @param  list<string>  $metricas
     * @param  array<string, mixed>  $filtros
     */
    private function construirAgrupado(string $agrupacion, array $metricas, array $filtros, string $agrupacionLabel): array
    {
        $metricasDef = self::metricasDisponibles();
        $metricasValidas = array_filter($metricas, fn ($m) => isset($metricasDef[$m]));
        if (empty($metricasValidas)) {
            $metricasValidas = ['total'];
        }

        $query = Cita::query()->with(['medico.user', 'medico.especialidad']);
        $this->aplicarFiltros($query, $filtros);
        $citas = $query->get();

        // Agrupar en PHP para compatibilidad SQLite/PostgreSQL
        $grupos = [];
        foreach ($citas as $c) {
            $key = $this->resolverClaveAgrupacion($c, $agrupacion);
            if (! isset($grupos[$key])) {
                $grupos[$key] = ['citas' => [], 'label' => $key];
            }
            $grupos[$key]['citas'][] = $c;
        }

        $columnas = [$this->etiquetaAgrupacion($agrupacion)];
        foreach ($metricasValidas as $m) {
            $columnas[] = $metricasDef[$m];
        }

        $filas = [];
        foreach ($grupos as $grupo) {
            $citasGrupo = $grupo['citas'];
            $total = count($citasGrupo);
            $atendidas = count(array_filter($citasGrupo, fn ($c) => $c->estado === 'atendida'));
            $canceladas = count(array_filter($citasGrupo, fn ($c) => $c->estado === 'cancelada'));
            $inasistencias = count(array_filter($citasGrupo, fn ($c) => $c->estado === 'inasistencia'));

            $fila = [$this->etiquetaAgrupacion($agrupacion) => $grupo['label']];
            foreach ($metricasValidas as $m) {
                $fila[$metricasDef[$m]] = match ($m) {
                    'total' => $total,
                    'tasa_asistencia' => $total > 0 ? round(($atendidas / $total) * 100, 1).'%' : '0%',
                    'cancelaciones' => $canceladas,
                    'inasistencias' => $inasistencias,
                    default => '—',
                };
            }
            $filas[] = $fila;
        }

        // Ordenar por total desc si existe la métrica
        if (in_array('total', $metricasValidas)) {
            $labelTotal = $metricasDef['total'];
            usort($filas, fn ($a, $b) => ($b[$labelTotal] ?? 0) <=> ($a[$labelTotal] ?? 0));
        }

        return [
            'columnas' => $columnas,
            'filas' => $filas,
            'total' => count($filas),
            'agrupacion_label' => $agrupacionLabel,
        ];
    }

    private function resolverClaveAgrupacion(Cita $cita, string $agrupacion): string
    {
        return match ($agrupacion) {
            'especialidad' => $cita->medico?->especialidad?->nombre ?? 'Sin especialidad',
            'medico' => $cita->medico?->user?->name ?? 'Sin médico',
            'estado' => ucfirst(str_replace('_', ' ', $cita->estado ?? 'desconocido')),
            'mes' => $cita->fecha ? Carbon::parse($cita->fecha)->format('Y-m (F Y)') : 'Sin fecha',
            'semana' => $cita->fecha ? 'Semana '.Carbon::parse($cita->fecha)->weekOfYear.' de '.Carbon::parse($cita->fecha)->year : 'Sin fecha',
            default => 'General',
        };
    }

    private function etiquetaAgrupacion(string $agrupacion): string
    {
        return match ($agrupacion) {
            'especialidad' => 'Especialidad',
            'medico' => 'Médico',
            'estado' => 'Estado',
            'mes' => 'Mes',
            'semana' => 'Semana',
            default => 'Grupo',
        };
    }

    private function extraerValor(Cita $cita, string $col): mixed
    {
        return match ($col) {
            'codigo_ficha' => $cita->codigo_ficha,
            'fecha' => $cita->fecha ? Carbon::parse($cita->fecha)->format('d/m/Y') : '',
            'hora' => $cita->hora ? substr($cita->hora, 0, 5) : '',
            'especialidad' => $cita->medico?->especialidad?->nombre ?? 'N/A',
            'medico' => $cita->medico?->user?->name ?? 'N/A',
            'paciente' => $cita->paciente ? trim(($cita->paciente->nombres ?? '').' '.($cita->paciente->apellidos ?? '')) : 'N/A',
            'ci' => $cita->paciente?->ci ?? 'N/A',
            'estado' => ucfirst(str_replace('_', ' ', $cita->estado ?? '')),
            'motivo_cancelacion' => $cita->motivo_cancelacion ?? '',
            'es_cupo_recuperado' => $cita->es_cupo_recuperado ? 'Sí' : 'No',
            'fecha_registro' => $cita->created_at ? Carbon::parse($cita->created_at)->format('d/m/Y H:i') : '',
            default => '',
        };
    }

    /** @param  array<string, mixed>  $filtros */
    private function aplicarFiltros(Builder $query, array $filtros): void
    {
        if (! empty($filtros['fecha_inicio'])) {
            $query->whereDate('fecha', '>=', $filtros['fecha_inicio']);
        }
        if (! empty($filtros['fecha_fin'])) {
            $query->whereDate('fecha', '<=', $filtros['fecha_fin']);
        }
        if (! empty($filtros['especialidad_id'])) {
            $query->whereHas('medico', fn ($q) => $q->where('especialidad_id', $filtros['especialidad_id']));
        }
        if (! empty($filtros['medico_id'])) {
            $query->where('medico_id', $filtros['medico_id']);
        }
        if (! empty($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        }
    }

    /**
     * Genera CSV con BOM UTF-8 (abre directamente en Excel sin configurar separadores).
     *
     * @param  list<string>  $columnas
     * @param  list<array<string, mixed>>  $filas
     * @param  string  $titulo  Incluido como comentario en las primeras filas
     */
    public function generarCsv(string $titulo, array $columnas, array $filas): string
    {
        $output = fopen('php://temp', 'r+');
        // UTF-8 BOM
        fwrite($output, "\xEF\xBB\xBF");
        // Metadatos del reporte
        fputcsv($output, ['Reporte', $titulo], ';', '"', '');
        fputcsv($output, ['Generado', Carbon::now()->format('d/m/Y H:i')], ';', '"', '');
        fputcsv($output, ['Total registros', count($filas)], ';', '"', '');
        fputcsv($output, [], ';', '"', ''); // línea vacía

        $escape = static fn (mixed $v): mixed => is_string($v) && preg_match('/^\s*[=+@-]/u', $v) ? "'".$v : $v;

        fputcsv($output, array_map($escape, $columnas), ';', '"', '');
        foreach ($filas as $fila) {
            fputcsv($output, array_map(fn ($col) => $escape($fila[$col] ?? ''), $columnas), ';', '"', '');
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return (string) $csv;
    }
}
