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
        $filtros = $request->only([
            'fecha_inicio',
            'fecha_fin',
            'especialidad_id',
            'medico_id',
            'estado',
        ]);

        $kpis = $this->indicadoresService->calcularKpis($filtros);
        $citas = $this->indicadoresService->obtenerListadoCitas($filtros, 15);
        $desgloseEstado = $this->indicadoresService->obtenerDesglosePorEstado($filtros);
        $desgloseEspecialidad = $this->indicadoresService->obtenerDesglosePorEspecialidad($filtros);

        $especialidades = Especialidad::where('activo', true)->orderBy('nombre')->get();
        $medicos = Medico::with('user', 'especialidad')
            ->where('estado', 'activo')
            ->get();

        $consultasIaRecientes = ReporteIa::with('user')
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
        $filtros = $request->only([
            'fecha_inicio',
            'fecha_fin',
            'especialidad_id',
            'medico_id',
            'estado',
        ]);

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
        $filtros = $request->only([
            'fecha_inicio',
            'fecha_fin',
            'especialidad_id',
            'medico_id',
            'estado',
        ]);

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
            'pregunta' => ['required', 'string', 'max:500'],
            'sql_manual' => ['nullable', 'string', 'max:1000'],
        ]);

        $resultado = $this->aiReporteService->ejecutarConsulta(
            pregunta: (string) $request->input('pregunta'),
            userId: (int) auth()->id(),
            sqlManual: $request->input('sql_manual')
        );

        return response()->json($resultado);
    }
}
