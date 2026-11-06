<?php

namespace App\Http\Controllers\P4_Citas;

use App\Http\Controllers\Controller;
use App\Models\P2\Especialidad;
use App\Models\P2\Medico;
use App\Models\P3\Paciente;
use App\Models\P4\ListaEspera;
use App\Models\P4\OfertaCupo;
use App\Services\AccesoPacienteService;
use App\Services\RecuperacionCupoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EsperaController extends Controller
{
    public function __construct(
        public AccesoPacienteService $acceso,
        public RecuperacionCupoService $recuperacion
    ) {}

    /**
     * Listado de inscripciones y ofertas de lista de espera (CU18).
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        if ($user->isPaciente()) {
            $pacientes = $this->acceso->pacientes($user)->get();
            $inscripciones = ListaEspera::with(['especialidad', 'medico.user', 'paciente', 'citaPrevia'])
                ->whereIn('paciente_id', $pacientes->pluck('id'))
                ->latest()
                ->paginate(10);

            $ofertasPendientes = OfertaCupo::with(['slot.medico.user', 'slot.medico.especialidad', 'listaEspera.paciente'])
                ->where('estado', 'pendiente')
                ->where('fecha_vencimiento', '>', now())
                ->whereHas('listaEspera', fn ($q) => $q->whereIn('paciente_id', $pacientes->pluck('id')))
                ->get();
        } else {
            // Personal administrativo / recepción
            $inscripciones = ListaEspera::with(['especialidad', 'medico.user', 'paciente', 'citaPrevia'])
                ->latest()
                ->paginate(15);

            $ofertasPendientes = OfertaCupo::with(['slot.medico.user', 'slot.medico.especialidad', 'listaEspera.paciente'])
                ->where('estado', 'pendiente')
                ->where('fecha_vencimiento', '>', now())
                ->get();
        }

        return view('p4_citas.espera.index', [
            'inscripciones' => $inscripciones,
            'ofertasPendientes' => $ofertasPendientes,
            'title' => 'Lista de Espera y Recuperación de Cupos (CU18) — MediCita Pro',
            'header' => 'Lista de Espera Clínica',
        ]);
    }

    /**
     * Formulario de inscripción a lista de espera (CU18).
     */
    public function create(Request $request): View
    {
        $user = $request->user();
        $pacientes = $this->acceso->pacientes($user)->where('activo', true)->get();
        $especialidades = Especialidad::where('activo', true)->orderBy('nombre')->get();
        $medicos = Medico::with('user')->where('estado', 'activo')->get();

        $pacientePreseleccionado = $request->filled('paciente_id')
            ? $pacientes->firstWhere('id', (int) $request->paciente_id)
            : $pacientes->first();

        $citasPaciente = collect();
        if ($pacientePreseleccionado) {
            $citasPaciente = $pacientePreseleccionado->citas()
                ->whereIn('estado', ['programada', 'confirmada'])
                ->where('fecha', '>=', today())
                ->get();
        }

        return view('p4_citas.espera.create', [
            'pacientes' => $pacientes,
            'pacientePreseleccionado' => $pacientePreseleccionado,
            'especialidades' => $especialidades,
            'medicos' => $medicos,
            'citasPaciente' => $citasPaciente,
            'title' => 'Inscribirse en Lista de Espera — MediCita Pro',
            'header' => 'Inscripción a Lista de Espera',
        ]);
    }

    /**
     * Guarda la inscripción a lista de espera (CU18).
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'paciente_id' => 'required|exists:pacientes,id',
            'especialidad_id' => 'required|exists:especialidades,id',
            'medico_id' => 'nullable|exists:medicos,id',
            'fecha_desde' => 'required|date|after_or_equal:today',
            'fecha_hasta' => 'required|date|after_or_equal:fecha_desde',
            'franja_horaria' => 'required|in:manana,tarde,cualquiera',
            'anticipacion_minima_horas' => 'required|integer|min:1|max:72',
            'cita_id' => 'nullable|exists:citas,id',
            'operacion_id' => 'nullable|uuid',
        ]);

        $paciente = Paciente::findOrFail($validated['paciente_id']);
        $lista = $this->recuperacion->inscribir($paciente, $validated, $request->user());

        return redirect()->route('espera.index')->with('success', 'Inscripción en lista de espera registrada. El sistema te ofrecerá automáticamente un cupo cuando se libere un horario compatible.');
    }

    /**
     * Muestra la oferta exclusiva de cupo (CU18).
     */
    public function showOferta(Request $request, OfertaCupo $oferta): View
    {
        $this->acceso->autorizar($request->user(), $oferta->listaEspera->paciente);
        $oferta->load(['slot.medico.user', 'slot.medico.especialidad', 'listaEspera.paciente', 'listaEspera.citaPrevia']);

        return view('p4_citas.espera.show_oferta', [
            'oferta' => $oferta,
            'title' => 'Oferta de Cupo Recuperado — MediCita Pro',
            'header' => 'Turno Recuperado Disponible',
        ]);
    }

    /**
     * Acepta la oferta y confirma la cita (CU18).
     */
    public function aceptar(Request $request, OfertaCupo $oferta): RedirectResponse
    {
        $cita = $this->recuperacion->aceptarOferta($oferta, $request->user(), $request->input('operacion_id'));

        return redirect()->route('citas.show', $cita)->with('success', "¡Turno aceptado exitosamente! Ficha asignada: {$cita->codigo_ficha}.");
    }

    /**
     * Rechaza la oferta y avanza al siguiente candidato (CU18).
     */
    public function rechazar(Request $request, OfertaCupo $oferta): RedirectResponse
    {
        $request->validate(['motivo' => 'nullable|string|max:500']);
        $this->recuperacion->rechazarOferta($oferta, $request->user(), $request->input('motivo'));

        return redirect()->route('espera.index')->with('info', 'Has declinado el turno ofrecido. Tu inscripción continúa activa para futuras liberaciones.');
    }

    /**
     * Cancela o retira una inscripción de lista de espera (CU18).
     */
    public function destroy(Request $request, ListaEspera $espera): RedirectResponse
    {
        $this->acceso->autorizar($request->user(), $espera->paciente);
        abort_unless($espera->isActiva(), 422, 'La inscripción no se encuentra activa.');

        $espera->update([
            'estado' => 'cancelada',
            'motivo_cancelacion' => 'Retirado por el usuario',
        ]);

        return redirect()->route('espera.index')->with('success', 'Inscripción en lista de espera cancelada.');
    }
}
