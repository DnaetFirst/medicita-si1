<?php

namespace App\Http\Controllers\P6_Reportes;

use App\Http\Controllers\Controller;
use App\Models\P2\Especialidad;
use App\Models\P2\Medico;
use App\Models\P4\Cita;
use App\Models\P6\ReporteIa;
use App\Services\AiReporteService;
use App\Services\ReporteConstructorService;
use App\Services\ReporteIndicadoresService;
use App\Services\ReportePdfService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ReporteController extends Controller
{
    public function __construct(
        protected ReporteIndicadoresService $indicadoresService,
        protected AiReporteService $aiReporteService,
        protected ReporteConstructorService $constructorService,
        protected ReportePdfService $pdfService
    ) {}

    /**
     * Panel principal de reportes analíticos e indicadores clínicos.
     */
    public function index(Request $request): View
    {
        $filtros = $this->filtros($request);

        $kpis = $this->indicadoresService->calcularKpis($filtros);
        $citas = $this->indicadoresService->obtenerListadoCitas($filtros, 15);
        $desgloseEstado = $this->indicadoresService->obtenerDesglosePorEstado($filtros);
        $desgloseEspecialidad = $this->indicadoresService->obtenerDesglosePorEspecialidad($filtros);

        $especialidades = Especialidad::where('activo', true)->orderBy('nombre')->get();
        $medicos = Medico::with('user', 'especialidad')
            ->where('estado', 'activo')
            ->get();

        $consultasIaRecientes = ReporteIa::with('user')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->limit(5)
            ->get();

        $columnasDisponibles = ReporteConstructorService::columnasDisponibles();
        $agrupacionesDisponibles = ReporteConstructorService::agrupacionesDisponibles();
        $metricasDisponibles = ReporteConstructorService::metricasDisponibles();

        return view('p6_reportes.index', compact(
            'kpis',
            'citas',
            'desgloseEstado',
            'desgloseEspecialidad',
            'especialidades',
            'medicos',
            'filtros',
            'consultasIaRecientes',
            'columnasDisponibles',
            'agrupacionesDisponibles',
            'metricasDisponibles'
        ));
    }

    // ──────────────────────────────────────────────────────────────────
    // TIPO 1: Exportaciones del reporte por filtros tradicionales
    // ──────────────────────────────────────────────────────────────────

    /**
     * CSV estructurado con metadatos (Excel-compatible, BOM UTF-8).
     */
    public function exportarCsv(Request $request): Response
    {
        $filtros = $this->filtros($request);
        $csv = $this->indicadoresService->generarCsv($filtros);
        $titulo = $this->tituloFiltros($filtros);
        $nombreArchivo = $this->nombreArchivo($titulo, 'csv');

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$nombreArchivo}\"",
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    /**
     * PDF binario descargable con KPIs y tabla de citas filtradas.
     */
    public function exportarPdfBinario(Request $request): Response
    {
        $filtros = $this->filtros($request);
        $kpis = $this->indicadoresService->calcularKpis($filtros);

        $query = Cita::query()
            ->with(['paciente', 'medico.user', 'medico.especialidad'])
            ->orderBy('fecha', 'desc')
            ->orderBy('hora', 'desc')
            ->limit(300);

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

        $citasRaw = $query->get();

        $columnas = ['Código', 'Fecha', 'Hora', 'Especialidad', 'Médico', 'Paciente', 'Estado'];
        $filas = $citasRaw->map(fn ($c) => [
            'Código' => $c->codigo_ficha ?? '',
            'Fecha' => $c->fecha ? Carbon::parse($c->fecha)->format('d/m/Y') : '',
            'Hora' => $c->hora ? substr($c->hora, 0, 5) : '',
            'Especialidad' => $c->medico?->especialidad?->nombre ?? 'N/A',
            'Médico' => $c->medico?->user?->name ?? 'N/A',
            'Paciente' => $c->paciente ? trim(($c->paciente->nombres ?? '').' '.($c->paciente->apellidos ?? '')) : 'N/A',
            'Estado' => ucfirst(str_replace('_', ' ', $c->estado ?? '')),
        ])->toArray();

        $titulo = $this->tituloFiltros($filtros);
        $pdf = $this->pdfService->generarReporteFiltros($titulo, $kpis, $filas, $filtros, $columnas);
        $nombreArchivo = $this->nombreArchivo($titulo, 'pdf');

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$nombreArchivo}\"",
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    /**
     * Vista de impresión (conservada para compatibilidad, abre nueva pestaña).
     */
    public function exportarPdf(Request $request): View
    {
        $filtros = $this->filtros($request);
        $kpis = $this->indicadoresService->calcularKpis($filtros);

        $query = Cita::query()
            ->with(['paciente.user', 'medico.user', 'medico.especialidad'])
            ->orderBy('fecha', 'desc')
            ->orderBy('hora', 'desc')
            ->limit(200);

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

        $citas = $query->get();
        $titulo = $this->tituloFiltros($filtros);

        return view('p6_reportes.print_pdf', compact('kpis', 'citas', 'filtros', 'titulo'));
    }

    // ──────────────────────────────────────────────────────────────────
    // TIPO 2: Constructor visual interactivo de reportes
    // ──────────────────────────────────────────────────────────────────

    /**
     * Construye y retorna JSON con la vista previa del reporte personalizado.
     */
    public function construirReporte(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'columnas' => ['nullable', 'array', 'max:11'],
            'columnas.*' => ['string', 'in:'.implode(',', array_keys(ReporteConstructorService::columnasDisponibles()))],
            'agrupacion' => ['nullable', 'string', 'in:'.implode(',', array_keys(ReporteConstructorService::agrupacionesDisponibles()))],
            'metricas' => ['nullable', 'array', 'max:4'],
            'metricas.*' => ['string', 'in:'.implode(',', array_keys(ReporteConstructorService::metricasDisponibles()))],
            'fecha_inicio' => ['nullable', 'date_format:Y-m-d'],
            'fecha_fin' => ['nullable', 'date_format:Y-m-d'],
            'especialidad_id' => ['nullable', 'integer', 'exists:especialidades,id'],
            'medico_id' => ['nullable', 'integer', 'exists:medicos,id'],
            'estado' => ['nullable', 'string'],
        ]);

        $filtros = array_filter([
            'fecha_inicio' => $datos['fecha_inicio'] ?? null,
            'fecha_fin' => $datos['fecha_fin'] ?? null,
            'especialidad_id' => $datos['especialidad_id'] ?? null,
            'medico_id' => $datos['medico_id'] ?? null,
            'estado' => $datos['estado'] ?? null,
        ]);

        $agrupacion = $datos['agrupacion'] ?? 'ninguna';
        $columnas = $datos['columnas'] ?? ['fecha', 'especialidad', 'medico', 'estado'];
        $metricas = $datos['metricas'] ?? ['total'];

        $resultado = $this->constructorService->construir($columnas, $agrupacion, $metricas, $filtros);
        $titulo = $this->constructorService->generarTitulo($agrupacion, $metricas, $filtros);

        return response()->json([
            'ok' => true,
            'titulo' => $titulo,
            'agrupacion_label' => $resultado['agrupacion_label'],
            'columnas' => $resultado['columnas'],
            'filas' => $resultado['filas'],
            'total' => $resultado['total'],
            'nombre_archivo' => $this->constructorService->generarNombreArchivo($titulo),
        ]);
    }

    /**
     * Exporta el reporte del constructor como CSV Excel-compatible.
     */
    public function exportarConstructorCsv(Request $request): Response
    {
        $datos = $request->validate([
            'columnas' => ['nullable', 'array', 'max:11'],
            'columnas.*' => ['string', 'in:'.implode(',', array_keys(ReporteConstructorService::columnasDisponibles()))],
            'agrupacion' => ['nullable', 'string', 'in:'.implode(',', array_keys(ReporteConstructorService::agrupacionesDisponibles()))],
            'metricas' => ['nullable', 'array', 'max:4'],
            'metricas.*' => ['string', 'in:'.implode(',', array_keys(ReporteConstructorService::metricasDisponibles()))],
            'fecha_inicio' => ['nullable', 'date_format:Y-m-d'],
            'fecha_fin' => ['nullable', 'date_format:Y-m-d'],
            'especialidad_id' => ['nullable', 'integer', 'exists:especialidades,id'],
            'medico_id' => ['nullable', 'integer', 'exists:medicos,id'],
            'estado' => ['nullable', 'string'],
        ]);

        $filtros = array_filter([
            'fecha_inicio' => $datos['fecha_inicio'] ?? null,
            'fecha_fin' => $datos['fecha_fin'] ?? null,
            'especialidad_id' => $datos['especialidad_id'] ?? null,
            'medico_id' => $datos['medico_id'] ?? null,
            'estado' => $datos['estado'] ?? null,
        ]);

        $agrupacion = $datos['agrupacion'] ?? 'ninguna';
        $columnas = $datos['columnas'] ?? ['fecha', 'especialidad', 'medico', 'estado'];
        $metricas = $datos['metricas'] ?? ['total'];

        $resultado = $this->constructorService->construir($columnas, $agrupacion, $metricas, $filtros);
        $titulo = $this->constructorService->generarTitulo($agrupacion, $metricas, $filtros);
        $csv = $this->constructorService->generarCsv($titulo, $resultado['columnas'], $resultado['filas']);
        $nombreArchivo = $this->constructorService->generarNombreArchivo($titulo).'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$nombreArchivo}\"",
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    /**
     * Exporta el reporte del constructor como PDF binario.
     */
    public function exportarConstructorPdf(Request $request): Response
    {
        $datos = $request->validate([
            'columnas' => ['nullable', 'array', 'max:11'],
            'columnas.*' => ['string', 'in:'.implode(',', array_keys(ReporteConstructorService::columnasDisponibles()))],
            'agrupacion' => ['nullable', 'string', 'in:'.implode(',', array_keys(ReporteConstructorService::agrupacionesDisponibles()))],
            'metricas' => ['nullable', 'array', 'max:4'],
            'metricas.*' => ['string', 'in:'.implode(',', array_keys(ReporteConstructorService::metricasDisponibles()))],
            'fecha_inicio' => ['nullable', 'date_format:Y-m-d'],
            'fecha_fin' => ['nullable', 'date_format:Y-m-d'],
            'especialidad_id' => ['nullable', 'integer', 'exists:especialidades,id'],
            'medico_id' => ['nullable', 'integer', 'exists:medicos,id'],
            'estado' => ['nullable', 'string'],
        ]);

        $filtros = array_filter([
            'fecha_inicio' => $datos['fecha_inicio'] ?? null,
            'fecha_fin' => $datos['fecha_fin'] ?? null,
            'especialidad_id' => $datos['especialidad_id'] ?? null,
            'medico_id' => $datos['medico_id'] ?? null,
            'estado' => $datos['estado'] ?? null,
        ]);

        $agrupacion = $datos['agrupacion'] ?? 'ninguna';
        $columnas = $datos['columnas'] ?? ['fecha', 'especialidad', 'medico', 'estado'];
        $metricas = $datos['metricas'] ?? ['total'];

        $resultado = $this->constructorService->construir($columnas, $agrupacion, $metricas, $filtros);
        $titulo = $this->constructorService->generarTitulo($agrupacion, $metricas, $filtros);

        $pdf = $this->pdfService->generarReportePersonalizado(
            $titulo,
            $resultado['columnas'],
            $resultado['filas'],
            $resultado['agrupacion_label']
        );

        $nombreArchivo = $this->constructorService->generarNombreArchivo($titulo).'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$nombreArchivo}\"",
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    // ──────────────────────────────────────────────────────────────────
    // TIPO 3: Asistente IA / Voz
    // ──────────────────────────────────────────────────────────────────

    /**
     * Endpoint API para consultas analíticas con asistente de IA conversacional y validador AST.
     */
    public function consultarIa(Request $request): JsonResponse
    {
        $request->validate([
            'pregunta' => ['required', 'string', 'max:1000'],
            'sql_manual' => ['prohibited'],
        ]);

        $resultado = $this->aiReporteService->ejecutarConsulta(
            pregunta: (string) $request->input('pregunta'),
            userId: (int) auth()->id(),
            contexto: $request->session()->get('reportes_ia.'.$request->user()->id, [])
        );

        if ($resultado['ok'] || $resultado['necesita_aclaracion']) {
            $contexto = $request->session()->get('reportes_ia.'.$request->user()->id, []);
            $contexto[] = ['pregunta' => $request->string('pregunta')->toString(), 'sql' => $resultado['sql'], 'aclaracion' => $resultado['necesita_aclaracion'] ? $resultado['mensaje'] : ''];
            $request->session()->put('reportes_ia.'.$request->user()->id, array_slice($contexto, -4));
        }

        if ($resultado['ok']) {
            $resultado['exportar_csv_url'] = route('reportes.ia.csv', $resultado['reporte_id']);
            $resultado['exportar_pdf_url'] = route('reportes.ia.pdf.binario', $resultado['reporte_id']);
            // Generar título descriptivo para el reporte IA
            $resultado['titulo_reporte'] = $this->tituloIa($request->string('pregunta')->toString(), $resultado['columnas'] ?? []);
        }

        return response()->json($resultado);
    }

    public function transcribirAudio(Request $request): JsonResponse
    {
        $request->validate(['audio' => ['required', 'file', 'max:10240', 'mimetypes:audio/webm,video/webm,audio/ogg,application/ogg,audio/wav,audio/x-wav,audio/mp4,audio/mpeg,audio/x-m4a']]);
        $audio = $request->file('audio');
        try {
            $mime = match ($audio->getMimeType()) {
                'audio/x-wav' => 'audio/wav',
                'application/ogg' => 'audio/ogg',
                'audio/x-m4a' => 'audio/mp4',
                default => $audio->getMimeType(),
            };
            $texto = $this->aiReporteService->transcribirAudio($audio->getContent(), $mime);

            return response()->json(['ok' => true, 'texto' => $texto]);
        } catch (\RuntimeException $exception) {
            return response()->json(['ok' => false, 'mensaje' => $exception->getMessage()], in_array($exception->getCode(), [422, 429]) ? $exception->getCode() : 503);
        }
    }

    /**
     * Exporta resultado IA como CSV descargable con nombre descriptivo.
     */
    public function exportarIaCsv(Request $request, ReporteIa $reporte): Response
    {
        $snapshot = $this->snapshot($request, $reporte);
        $titulo = mb_substr($reporte->pregunta_usuario ?? 'Reporte IA', 0, 80);

        $archivo = fopen('php://temp', 'w+');
        fwrite($archivo, "\xEF\xBB\xBF");
        // Metadatos del reporte IA
        $escapar = static fn (mixed $valor): mixed => is_string($valor) && preg_match('/^[\s]*[=+@-]/u', $valor) ? "'".$valor : $valor;
        fputcsv($archivo, ['Consulta IA', $titulo], ';', '"', '');
        fputcsv($archivo, ['Generado', Carbon::now()->format('d/m/Y H:i')], ';', '"', '');
        fputcsv($archivo, ['Total filas', count($snapshot['filas'])], ';', '"', '');
        fputcsv($archivo, [], ';', '"', '');
        fputcsv($archivo, array_map($escapar, $snapshot['columnas']), ';', '"', '');
        foreach ($snapshot['filas'] as $fila) {
            fputcsv($archivo, array_map(fn (string $columna): mixed => $escapar($fila[$columna] ?? ''), $snapshot['columnas']), ';', '"', '');
        }
        rewind($archivo);
        $csv = stream_get_contents($archivo);
        fclose($archivo);

        $nombre = $this->constructorService->generarNombreArchivo('reporte_ia_'.mb_substr(preg_replace('/[^a-z0-9]/i', '_', $titulo) ?? 'consulta', 0, 40));
        $nombreArchivo = "{$nombre}.csv";

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$nombreArchivo}\"",
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * Exporta resultado IA como PDF binario descargable.
     */
    public function exportarIaPdfBinario(Request $request, ReporteIa $reporte): Response
    {
        $snapshot = $this->snapshot($request, $reporte);
        $pregunta = $reporte->pregunta_usuario ?? 'Consulta IA';
        $titulo = $this->tituloIa($pregunta, $snapshot['columnas']);

        $pdf = $this->pdfService->generarReporteIa(
            $titulo,
            $pregunta,
            $snapshot['columnas'],
            $snapshot['filas']
        );

        $nombre = $this->constructorService->generarNombreArchivo(mb_substr(preg_replace('/[^a-z0-9]/i', '_', $titulo) ?? 'reporte_ia', 0, 50));
        $nombreArchivo = "{$nombre}.pdf";

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$nombreArchivo}\"",
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * Vista de impresión HTML para resultado IA (retiene compatibilidad).
     */
    public function exportarIaPdf(Request $request, ReporteIa $reporte): View
    {
        return view('p6_reportes.ia_pdf', ['reporte' => $reporte, 'snapshot' => $this->snapshot($request, $reporte)]);
    }

    // ──────────────────────────────────────────────────────────────────
    // Helpers privados
    // ──────────────────────────────────────────────────────────────────

    /** @return array{columnas: list<string>, filas: list<array<string, mixed>>} */
    private function snapshot(Request $request, ReporteIa $reporte): array
    {
        abort_unless($reporte->user_id === $request->user()->id, 403);
        abort_unless($reporte->es_valido && $reporte->resultado_snapshot, 422, 'Este reporte no tiene un resultado conservado. Ejecuta nuevamente la consulta.');

        return $reporte->resultado_snapshot;
    }

    /** @return array<string, mixed> */
    private function filtros(Request $request): array
    {
        return $request->validate([
            'fecha_inicio' => 'nullable|date_format:Y-m-d',
            'fecha_fin' => ['nullable', 'date_format:Y-m-d', ...($request->filled('fecha_inicio') ? ['after_or_equal:fecha_inicio'] : [])],
            'especialidad_id' => 'nullable|integer|exists:especialidades,id',
            'medico_id' => 'nullable|integer|exists:medicos,id',
            'estado' => 'nullable|in:programada,confirmada,en_espera,en_atencion,atendida,postergada,cancelada,inasistencia',
        ]);
    }

    /**
     * Genera un título legible a partir de los filtros activos.
     *
     * @param  array<string, mixed>  $filtros
     */
    private function tituloFiltros(array $filtros): string
    {
        $partes = [];
        if (! empty($filtros['estado'])) {
            $partes[] = ucfirst(str_replace('_', ' ', $filtros['estado']));
        } else {
            $partes[] = 'Todas las Citas';
        }
        if (! empty($filtros['fecha_inicio']) && ! empty($filtros['fecha_fin'])) {
            $partes[] = Carbon::parse($filtros['fecha_inicio'])->format('d/m/Y').' — '.Carbon::parse($filtros['fecha_fin'])->format('d/m/Y');
        } elseif (! empty($filtros['fecha_inicio'])) {
            $partes[] = 'Desde '.Carbon::parse($filtros['fecha_inicio'])->format('d/m/Y');
        }

        return 'Reporte '.implode(' · ', $partes);
    }

    /**
     * Genera nombre de archivo limpio con prefijo explicito.
     */
    private function nombreArchivo(string $titulo, string $extension): string
    {
        $nombre = mb_strtolower($titulo);
        $nombre = str_replace([' ', '—', '/', '(', ')', '·', ':'], ['_', '_', '_', '', '', '_', ''], $nombre);
        $nombre = preg_replace('/[^a-z0-9_áéíóúñ]/u', '', $nombre) ?? '';
        $nombre = preg_replace('/_+/', '_', $nombre) ?? '';
        $nombre = preg_replace('/^reporte_/', '', trim($nombre, '_'));
        $fecha = Carbon::now()->format('Ymd_His');

        return "reporte_citas_{$nombre}_{$fecha}.{$extension}";
    }

    /**
     * Genera título legible para reporte IA basado en la pregunta y columnas.
     *
     * @param  list<string>  $columnas
     */
    private function tituloIa(string $pregunta, array $columnas): string
    {
        $preguntaCorta = mb_substr($pregunta, 0, 60).(mb_strlen($pregunta) > 60 ? '…' : '');

        return 'Reporte IA — '.$preguntaCorta;
    }
}
