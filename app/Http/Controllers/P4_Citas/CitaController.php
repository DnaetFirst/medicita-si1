<?php

namespace App\Http\Controllers\P4_Citas;

use App\Http\Controllers\Controller;
use App\Models\P2\Especialidad;
use App\Models\P2\Medico;
use App\Models\P4\Cita;
use App\Services\AccesoPacienteService;
use App\Services\CitaWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CitaController extends Controller
{
    /**
     * Tablero de control y gestión de citas médicas (CU10).
     */
    public function index(Request $request): View
    {
        app(CitaWorkflowService::class)->procesarInasistenciasAutomaticas();

        $query = Cita::query();
        if ($request->user()->isMedico()) {
            $query->where('medico_id', $request->user()->medico?->id ?? 0);
        }
        $query->with(['paciente', 'medico.user', 'medico.especialidad'])->latest('fecha');

        // Filtro por fecha (por defecto hoy si no se envía filtro o si se especifica)
        if ($request->filled('fecha')) {
            $query->whereDate('fecha', $request->fecha);
        }

        if (! $request->user()->isMedico() && $request->filled('medico_id')) {
            $query->where('medico_id', $request->medico_id);
        }

        if ($request->filled('especialidad_id')) {
            $query->whereHas('medico', fn ($q) => $q->where('especialidad_id', $request->especialidad_id));
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('codigo_ficha', 'like', "%{$search}%")
                    ->orWhereHas('paciente', function ($qu) use ($search) {
                        $qu->where('nombres', 'like', "%{$search}%")
                            ->orWhere('apellidos', 'like', "%{$search}%")
                            ->orWhere('ci', 'like', "%{$search}%");
                    });
            });
        }

        $citas = $query->paginate(15)->withQueryString();

        $medicos = $request->user()->isMedico()
            ? Medico::with('user')->where('id', $request->user()->medico?->id ?? 0)->get()
            : Medico::with('user')->where('estado', 'activo')->get();
        $especialidades = Especialidad::where('activo', true)->get();

        $dailyQuery = Cita::whereDate('fecha', today());
        if ($request->user()->isMedico()) {
            $dailyQuery->where('medico_id', $request->user()->medico?->id ?? 0);
        }
        $kpis = [
            'total_hoy' => (clone $dailyQuery)->count(),
            'programadas' => (clone $dailyQuery)->where('estado', 'programada')->count(),
            'confirmadas_checkin' => (clone $dailyQuery)->where('estado', 'en_espera')->count(),
            'atendidas' => (clone $dailyQuery)->where('estado', 'atendida')->count(),
            'canceladas' => (clone $dailyQuery)->where('estado', 'cancelada')->count(),
        ];

        return view('p4_citas.citas.index', [
            'citas' => $citas,
            'medicos' => $medicos,
            'especialidades' => $especialidades,
            'kpis' => $kpis,
            'filters' => $request->all(),
            'title' => 'Gestión de Citas Médicas (CU10) — MediCita Pro',
            'header' => 'Control de Citas Médicas y Recepción',
        ]);
    }

    /**
     * Muestra el comprobante y ficha digital de cita médica (CU9).
     */
    public function show(Cita $cita): View
    {
        $this->autorizarCita($cita);
        $cita->load(['paciente.responsableActivo', 'medico.user', 'medico.especialidad', 'slot', 'canceladoPor']);

        return view('p4_citas.citas.show', [
            'cita' => $cita,
            'title' => "Comprobante: {$cita->codigo_ficha} — MediCita Pro",
            'header' => 'Comprobante de Ficha Médica',
        ]);
    }

    public function confirmar(Request $request, Cita $cita): RedirectResponse
    {
        return $this->transicion($request, $cita, 'confirmar');
    }

    public function checkin(Request $request, Cita $cita): RedirectResponse
    {
        return $this->transicion($request, $cita, 'llegada');
    }

    public function iniciarAtencion(Request $request, Cita $cita): RedirectResponse
    {
        return $this->transicion($request, $cita, 'iniciar');
    }

    public function finalizarAtencion(Request $request, Cita $cita): RedirectResponse
    {
        return $this->transicion($request, $cita, 'finalizar');
    }

    public function marcarInasistencia(Request $request, Cita $cita): RedirectResponse
    {
        return $this->transicion($request, $cita, 'inasistencia');
    }

    protected function transicion(Request $request, Cita $cita, string $accion): RedirectResponse
    {
        $datos = $request->validate(['operacion_id' => 'nullable|uuid', 'version' => 'nullable|integer|min:1']);
        app(CitaWorkflowService::class)->ejecutar($cita, $request->user(), $accion, $datos);

        return back()->with('success', 'Estado actualizado y registrado en el historial.');
    }

    public function cancelar(Request $request, Cita $cita): RedirectResponse
    {
        app(AccesoPacienteService::class)->autorizarCita($request->user(), $cita, 'cancelar');
        $datos = $request->validate(['motivo_cancelacion' => 'required|string|min:5|max:500', 'operacion_id' => 'nullable|uuid', 'version' => 'nullable|integer|min:1']);
        $datos['motivo'] = $datos['motivo_cancelacion'];
        app(CitaWorkflowService::class)->ejecutar($cita, $request->user(), 'cancelar', $datos);

        return redirect()->route('citas.show', $cita)->with('success', 'Cita cancelada. La disponibilidad del cupo fue recalculada.');
    }

    public function reprogramarView(Cita $cita): View
    {
        app(AccesoPacienteService::class)->autorizarCita(auth()->user(), $cita, 'reprogramar');
        abort_unless(in_array($cita->estado, ['programada', 'confirmada', 'postergada']), 422);
        $medicos = Medico::with('user')->where('especialidad_id', $cita->medico->especialidad_id)->where('estado', 'activo')->get();

        return view('p4_citas.citas.reprogramar', compact('cita', 'medicos'));
    }

    public function reprogramar(Request $request, Cita $cita): RedirectResponse
    {
        app(AccesoPacienteService::class)->autorizarCita($request->user(), $cita, 'reprogramar');
        $datos = $request->validate([
            'nuevo_slot_id' => 'required|integer|exists:slots,id', 'motivo_reprogramacion' => 'required|string|max:500',
            'aceptacion_paciente' => 'required|accepted', 'operacion_id' => 'nullable|uuid', 'version' => 'nullable|integer|min:1',
        ]);
        $datos['motivo'] = $datos['motivo_reprogramacion'];
        app(CitaWorkflowService::class)->ejecutar($cita, $request->user(), 'reprogramar', $datos);

        return redirect()->route('citas.show', $cita)->with('success', 'Cita reprogramada conservando su ficha e historial.');
    }

    protected function autorizarCita(Cita $cita): void
    {
        app(AccesoPacienteService::class)->autorizarCita(auth()->user(), $cita);
    }

    public function misCitas(): RedirectResponse
    {
        return redirect()->route('historial.index');
    }
}
