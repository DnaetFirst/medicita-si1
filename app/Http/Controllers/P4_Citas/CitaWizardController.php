<?php

namespace App\Http\Controllers\P4_Citas;

use App\Http\Controllers\Controller;
use App\Models\P2\Especialidad;
use App\Models\P2\Medico;
use App\Models\P2\Slot;
use App\Models\P3\Paciente;
use App\Models\P4\Cita;
use App\Services\BitacoraService;
use App\Services\SlotGeneratorService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CitaWizardController extends Controller
{
    /**
     * Vista del asistente guiado de 4 pasos para solicitar ficha médica (CU8).
     */
    public function create(Request $request)
    {
        $especialidades = Especialidad::where('activo', true)
            ->withCount(['medicos' => fn($q) => $q->where('estado', 'activo')])
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

        $pacientes = Paciente::where('activo', true)->orderBy('nombres')->limit(50)->get();
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
    public function store(Request $request, SlotGeneratorService $slotGenerator)
    {
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

            $paciente = Paciente::create([
                'ci' => $validatedPaciente['nuevo_ci'],
                'nombres' => $validatedPaciente['nuevo_nombres'],
                'apellidos' => $validatedPaciente['nuevo_apellidos'],
                'fecha_nacimiento' => $validatedPaciente['nuevo_fecha_nacimiento'],
                'telefono' => $validatedPaciente['nuevo_telefono'],
                'activo' => true,
            ]);

            $pacienteId = $paciente->id;
        } else {
            $request->validate([
                'paciente_id' => 'required|exists:pacientes,id',
            ], [
                'paciente_id.required' => 'Debes seleccionar el paciente para quien se reserva la ficha médica.',
            ]);
            $pacienteId = $request->paciente_id;
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

        $cita = DB::transaction(function () use ($request, $pacienteId) {
            // Bloqueo pesimista para evitar colisiones concurrentes (doble reserva)
            $slot = Slot::where('id', $request->slot_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($slot->estado !== 'disponible') {
                abort(422, 'El turno seleccionado ya no se encuentra disponible. Por favor elija otro horario.');
            }

            // Marcar slot como reservado
            $slot->update(['estado' => 'reservado']);

            // Generar código único de ficha médica (FCH-YYYYMMDD-XXXX)
            $fechaCode = Carbon::parse($slot->fecha)->format('Ymd');
            $correlativo = Cita::whereDate('created_at', today())->count() + 1;
            $codigoFicha = 'FCH-' . $fechaCode . '-' . str_pad($correlativo, 4, '0', STR_PAD_LEFT);

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

        return redirect()->route('citas.show', $cita)
            ->with('success', "¡Ficha médica {$cita->codigo_ficha} reservada con éxito! Turno confirmado a las {$cita->hora}.");
    }
}
