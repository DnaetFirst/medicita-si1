<?php

namespace App\Http\Controllers\P6_Reportes;

use App\Http\Controllers\Controller;
use App\Models\P2\Especialidad;
use App\Models\P2\Medico;
use App\Models\P4\Cita;
use App\Models\P6\ReporteIa;
use App\Services\AiReporteService;
use App\Services\ReporteIndicadoresService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ReporteController extends Controller
{
    public function __construct(
        protected ReporteIndicadoresService $indicadoresService,
        protected AiReporteService $aiReporteService
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

        return view('p6_reportes.index', compact(
            'kpis',
            'citas',
            'desgloseEstado',
            'desgloseEspecialidad',
            'especialidades',
            'medicos',
            'filtros',
            'consultasIaRecientes'
        ));
    }

    /**
     * Exportación de citas a formato CSV estructurado.
     */
    public function exportarCsv(Request $request): Response
    {
        $filtros = $this->filtros($request);

        $csv = $this->indicadoresService->generarCsv($filtros);
        $fecha = Carbon::now()->format('Ymd_His');
        $nombreArchivo = "reporte_citas_{$fecha}.csv";

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$nombreArchivo}\"",
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    /**
     * Vista de impresión maquetada para PDF / Impresora.
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

        return view('p6_reportes.print_pdf', compact('kpis', 'citas', 'filtros'));
    }

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
            $resultado['exportar_pdf_url'] = route('reportes.ia.pdf', $resultado['reporte_id']);
        }

        return response()->json($resultado);
    }

    public function transcribirAudio(Request $request): JsonResponse
    {
        $request->validate(['audio' => ['required', 'file', 'max:10240', 'mimetypes:audio/webm,video/webm,audio/ogg,application/ogg,audio/wav,audio/x-wav,audio/mp4,audio/mpeg,audio/x-m4a']]);
        $audio = $request->file('audio');
        try {
            $mime = match ($audio->getMimeType()) {
                'audio/x-wav' => 'audio/wav', 'application/ogg' => 'audio/ogg', 'audio/x-m4a' => 'audio/mp4',
                default => $audio->getMimeType(),
            };
            $texto = $this->aiReporteService->transcribirAudio($audio->getContent(), $mime);

            return response()->json(['ok' => true, 'texto' => $texto]);
        } catch (\RuntimeException $exception) {
            return response()->json(['ok' => false, 'mensaje' => $exception->getMessage()], in_array($exception->getCode(), [422, 429]) ? $exception->getCode() : 503);
        }
    }

    public function exportarIaCsv(Request $request, ReporteIa $reporte): Response
    {
        $snapshot = $this->snapshot($request, $reporte);
        $archivo = fopen('php://temp', 'w+');
        fwrite($archivo, "\xEF\xBB\xBF");
        $escapar = static fn (mixed $valor): mixed => is_string($valor) && preg_match('/^[\s]*[=+@-]/u', $valor) ? "'".$valor : $valor;
        fputcsv($archivo, array_map($escapar, $snapshot['columnas']), ',', '"', '');
        foreach ($snapshot['filas'] as $fila) {
            fputcsv($archivo, array_map(fn (string $columna): mixed => $escapar($fila[$columna] ?? ''), $snapshot['columnas']), ',', '"', '');
        }
        rewind($archivo);
        $csv = stream_get_contents($archivo);
        fclose($archivo);

        return response($csv, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="reporte-ia-'.$reporte->id.'.csv"', 'Cache-Control' => 'private, no-store']);
    }

    public function exportarIaPdf(Request $request, ReporteIa $reporte): View
    {
        return view('p6_reportes.ia_pdf', ['reporte' => $reporte, 'snapshot' => $this->snapshot($request, $reporte)]);
    }

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
}
