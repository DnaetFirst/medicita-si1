<?php

namespace App\Http\Controllers\P3_Pacientes;

use App\Http\Controllers\Controller;
use App\Models\P2\Especialidad;
use App\Models\P2\Medico;
use App\Models\P3\Derivacion;
use App\Models\P4\Cita;
use App\Services\AccesoPacienteService;
use App\Services\BitacoraService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HistorialController extends Controller
{
    public function index(Request $request, AccesoPacienteService $acceso): View
    {
        abort_unless($request->user()->isPaciente() || $request->user()->isRecepcionista() || $request->user()->isMedico() || $request->user()->isAdmin(), 403);
        $datos = $request->validate([
            'paciente_id' => 'nullable|integer', 'desde' => 'nullable|date_format:Y-m-d',
            'hasta' => ['nullable', 'date_format:Y-m-d', ...($request->filled('desde') ? ['after_or_equal:desde'] : [])], 'medico_id' => 'nullable|integer|exists:medicos,id',
            'especialidad_id' => 'nullable|integer|exists:especialidades,id',
            'estado' => ['nullable', Rule::in(['programada', 'confirmada', 'en_espera', 'en_atencion', 'atendida', 'cancelada', 'postergada', 'inasistencia'])],
        ]);
        $pacientes = $acceso->pacientes($request->user())->orderBy('nombres')->get();

        $paciente = null;
        if ($request->filled('paciente_id')) {
            $paciente = $pacientes->firstWhere('id', (int) $datos['paciente_id']);
            if ($request->user()->isPaciente() && ! $paciente) {
                abort(403);
            }
        } elseif ($request->user()->isPaciente()) {
            $paciente = $pacientes->first();
        }

        $query = Cita::with(['medico.user', 'medico.especialidad', 'historial.autor']);

        // Cada médico ve estrictamente solo sus citas asignadas
        if ($request->user()->isMedico()) {
            $query->where('medico_id', $request->user()->medico?->id ?? 0);
        } elseif (! empty($datos['medico_id'])) {
            $query->where('medico_id', $datos['medico_id']);
        }

        // Filtro por paciente o conjunto permitido
        if ($request->filled('paciente_id')) {
            if ($paciente) {
                $query->where('paciente_id', $paciente->id);
            } else {
                // Paciente filtrado no encontrado o sin citas vinculadas -> mostrar vacío
                $query->whereRaw('1 = 0');
            }
        } elseif ($request->user()->isPaciente()) {
            $query->where('paciente_id', $paciente?->id ?? 0);
        } elseif ($request->user()->isMedico()) {
            $query->whereIn('paciente_id', $pacientes->pluck('id'));
        }

        if (! empty($datos['estado'])) {
            $query->where('estado', $datos['estado']);
        }
        if (! empty($datos['desde'])) {
            $query->whereDate('fecha', '>=', $datos['desde']);
        }
        if (! empty($datos['hasta'])) {
            $query->whereDate('fecha', '<=', $datos['hasta']);
        }
        if (! empty($datos['especialidad_id'])) {
            $query->whereHas('medico', fn ($q) => $q->where('especialidad_id', $datos['especialidad_id']));
        }
        $citas = $query->orderByDesc('fecha')->orderByDesc('hora')->orderByDesc('id')->paginate(10)->withQueryString();
        if ($paciente) {
            BitacoraService::registrar('CONSULTAR_HISTORIAL', 'P3_Pacientes', ['paciente_id' => $paciente->id]);
        }

        $derivaciones = collect();
        if ($paciente && ! $request->user()->isMedico()) {
            $derivaciones = Derivacion::with('especialidad')
                ->where('estado', 'emitida')
                ->whereHas('atencion.historia', fn ($q) => $q->where('paciente_id', $paciente->id))
                ->get();
        }

        $medicos = $request->user()->isMedico()
            ? Medico::with('user')->where('id', $request->user()->medico?->id ?? 0)->get()
            : Medico::with('user')->where('estado', 'activo')->get();

        return view('p3_pacientes.historial.index', [
            'pacientes' => $pacientes, 'paciente' => $paciente, 'citas' => $citas,
            'derivaciones' => $derivaciones,
            'especialidades' => Especialidad::where('activo', true)->orderBy('nombre')->get(), 'medicos' => $medicos,
            'header' => 'Historial de citas',
        ]);
    }
}
