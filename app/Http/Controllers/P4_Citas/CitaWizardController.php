<?php

namespace App\Http\Controllers\P4_Citas;

use App\Http\Controllers\Controller;
use App\Models\P2\Especialidad;
use App\Models\P2\Medico;
use App\Models\P2\Slot;
use App\Models\P3\Paciente;
use App\Models\P4\Cita;
use App\Services\BitacoraService;
use App\Services\NotificacionService;
use App\Services\SlotGeneratorService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CitaWizardController extends Controller
{
    /**
     * Vista del asistente guiado de 4 pasos para solicitar ficha médica (CU8).
     */
    public function create(Request $request): View
    {
        $request->validate([
            'especialidad_id' => 'nullable|integer|exists:especialidades,id',
            'medico_id' => 'nullable|integer|exists:medicos,id',
            'paciente_id' => 'nullable|integer|exists:pacientes,id',
            'fecha' => 'nullable|date_format:Y-m-d',
        ]);
        $especialidades = Especialidad::where('activo', true)
            ->with(['medicos' => function ($query) use ($request): void {
                $query->with('user')->where('estado', 'activo')->whereHas('user', fn ($userQuery) => $userQuery->where('estado', 'activo'));
                if ($request->user()->isMedico()) {
                    $query->where('id', $request->user()->medico?->id ?? 0);
                }
            }])
            ->withCount(['medicos' => fn ($q) => $q->where('estado', 'activo')])
            ->get();

        $selectedEspecialidadId = $request->input('especialidad_id');
        $selectedMedicoId = $request->input('medico_id');
        $selectedFecha = $request->input('fecha', today()->toDateString());
        $selectedPacienteId = $request->input('paciente_id');

        $medicos = collect();
        if ($selectedEspecialidadId) {
            $medicos = Medico::with('user')
                ->where('especialidad_id', $selectedEspecialidadId)
                ->where('estado', 'activo')
                ->get();
        }

        $pacientes = Paciente::where('activo', true)->orderBy('nombres')->get();
        $pacientePreseleccionado = $selectedPacienteId ? Paciente::find($selectedPacienteId) : null;

        return view('p4_citas.citas.wizard', [
            'especialidades' => $especialidades,
            'medicos' => $medicos,
            'pacientes' => $pacientes,
            'pacientePreseleccionado' => $pacientePreseleccionado,
            'selectedEspecialidadId' => $selectedEspecialidadId,
            'selectedMedicoId' => $selectedMedicoId,
            'selectedFecha' => $selectedFecha,
            'selectedPacienteId' => $selectedPacienteId,
            'title' => 'Solicitud de Ficha y Reserva de Citas (CU8) — MediCita Pro',
            'header' => 'Asistente de Reserva de Citas Médicas',
        ]);
    }

    /**
     * Procesa la reserva atómica del turno y emite la ficha médica (CU8).
     */
    public function store(Request $request, SlotGeneratorService $slotGenerator): RedirectResponse
    {
        $validatedPaciente = null;

        // 1. Si se solicita registro de un nuevo paciente inline
        if ($request->boolean('crear_paciente_nuevo')) {
            $validatedPaciente = $request->validate([
                'nuevo_ci' => 'required|string|max:20|unique:pacientes,ci',
                'nuevo_nombres' => 'required|string|max:100',
                'nuevo_apellidos' => 'required|string|max:100',
                'nuevo_fecha_nacimiento' => 'required|date|before:today',
                'nuevo_telefono' => 'required|string|max:20',
            ], [
                'nuevo_ci.unique' => 'Ya existe un paciente registrado con este número de CI.',
                'nuevo_nombres.required' => 'El nombre del paciente es obligatorio.',
                'nuevo_apellidos.required' => 'El apellido del paciente es obligatorio.',
                'nuevo_telefono.required' => 'El teléfono de contacto es obligatorio.',
            ]);

        } else {
            $request->validate([
                'paciente_id' => ['required', Rule::exists('pacientes', 'id')->where('activo', true)],
            ], [
                'paciente_id.required' => 'Debes seleccionar el paciente para quien se reserva la ficha médica.',
            ]);
        }

        // 2. Validación de la reserva
        $request->validate([
            'medico_id' => 'required|exists:medicos,id',
            'slot_id' => 'required|exists:slots,id',
            'motivo_consulta' => 'nullable|string|max:500',
        ], [
            'medico_id.required' => 'Debes seleccionar el médico.',
            'slot_id.required' => 'Debes seleccionar un turno de 15 minutos disponible.',
        ]);

        $cita = DB::transaction(function () use ($request, $validatedPaciente) {
            // Bloqueo pesimista para evitar colisiones concurrentes (doble reserva)
            $slot = Slot::where('id', $request->slot_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($slot->estado !== 'disponible') {
                abort(422, 'El turno seleccionado ya no se encuentra disponible. Por favor elija otro horario.');
            }

            abort_if($request->user()->isMedico() && $request->user()->medico?->id !== $slot->medico_id, 403);
            abort_unless((int) $slot->medico_id === $request->integer('medico_id'), 422, 'El turno no pertenece al medico seleccionado.');
            abort_unless($slot->medico->estado === 'activo' && $slot->medico->user->estado === 'activo' && $slot->medico->especialidad->activo, 422, 'El medico o su especialidad no estan disponibles.');
            abort_if(Carbon::parse($slot->fecha->toDateString().' '.$slot->hora_inicio)->lte(now()), 422, 'No se pueden reservar turnos pasados.');
            abort_if(app(SlotGeneratorService::class)->estaBloqueado($slot), 422, 'El turno esta bloqueado.');

            $pacienteId = $request->integer('paciente_id');
            if ($validatedPaciente !== null) {
                $pacienteId = Paciente::create([
                    'ci' => $validatedPaciente['nuevo_ci'],
                    'nombres' => $validatedPaciente['nuevo_nombres'],
                    'apellidos' => $validatedPaciente['nuevo_apellidos'],
                    'fecha_nacimiento' => $validatedPaciente['nuevo_fecha_nacimiento'],
                    'telefono' => $validatedPaciente['nuevo_telefono'],
                    'activo' => true,
                ])->id;
            }

            $slot->update(['estado' => 'reservado']);
            $codigoFicha = 'FCH-'.$slot->fecha->format('Ymd').'-'.strtoupper(Str::random(16));

            $nuevaCita = Cita::create([
                'codigo_ficha' => $codigoFicha,
                'paciente_id' => $pacienteId,
                'medico_id' => $request->medico_id,
                'slot_id' => $slot->id,
                'fecha' => $slot->fecha,
                'hora' => $slot->hora_inicio,
                'estado' => 'programada',
                'motivo_consulta' => $request->motivo_consulta,
            ]);

            BitacoraService::registrar('SOLICITAR_FICHA_CITA', 'P4_Citas', [
                'cita_id' => $nuevaCita->id,
                'codigo_ficha' => $codigoFicha,
                'paciente_id' => $pacienteId,
                'medico_id' => $request->medico_id,
                'fecha' => $slot->fecha,
                'hora' => $slot->hora_inicio,
            ]);

            return $nuevaCita;
        });

        // Notificación de confirmación por email y aviso en sistema (CU16)
        NotificacionService::notificarConfirmacion($cita);

        return redirect()->route('citas.show', $cita)
            ->with('success', "¡Ficha médica {$cita->codigo_ficha} reservada con éxito! Turno confirmado a las {$cita->hora}.");
    }
}
