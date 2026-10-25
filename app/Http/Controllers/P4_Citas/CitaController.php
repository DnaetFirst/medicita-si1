<?php

namespace App\Http\Controllers\P4_Citas;

use App\Http\Controllers\Controller;
use App\Models\P2\Especialidad;
use App\Models\P2\Medico;
use App\Models\P2\Slot;
use App\Models\P4\Cita;
use App\Services\BitacoraService;
use App\Services\SlotGeneratorService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CitaController extends Controller
{
    /**
     * Tablero de control y gestión de citas médicas (CU10).
     */
    public function index(Request $request)
    {
        $query = Cita::with(['paciente', 'medico.user', 'medico.especialidad'])->latest('fecha');

        // Filtro por fecha (por defecto hoy si no se envía filtro o si se especifica)
        if ($request->filled('fecha')) {
            $query->whereDate('fecha', $request->fecha);
        }

        if ($request->filled('medico_id')) {
            $query->where('medico_id', $request->medico_id);
        }

        if ($request->filled('especialidad_id')) {
            $query->whereHas('medico', fn($q) => $q->where('especialidad_id', $request->especialidad_id));
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

        $medicos = Medico::with('user')->where('estado', 'activo')->get();
        $especialidades = Especialidad::where('activo', true)->get();

        $kpis = [
            'total_hoy' => Cita::whereDate('fecha', today())->count(),
            'programadas' => Cita::whereDate('fecha', today())->where('estado', 'programada')->count(),
            'confirmadas_checkin' => Cita::whereDate('fecha', today())->where('estado', 'confirmada')->count(),
            'atendidas' => Cita::whereDate('fecha', today())->where('estado', 'atendida')->count(),
            'canceladas' => Cita::whereDate('fecha', today())->where('estado', 'cancelada')->count(),
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
    public function show(Cita $cita)
    {
        $cita->load(['paciente.responsableActivo', 'medico.user', 'medico.especialidad', 'slot', 'canceladoPor']);

        return view('p4_citas.citas.show', [
            'cita' => $cita,
            'title' => "Comprobante: {$cita->codigo_ficha} — MediCita Pro",
            'header' => 'Comprobante de Ficha Médica',
        ]);
    }

    /**
     * Realiza el Check-in del paciente en recepción (CU10).
     */
    public function checkin(Cita $cita)
    {
        if ($cita->estado === 'cancelada') {
            return back()->with('error', 'No se puede realizar check-in a una cita cancelada.');
        }

        $cita->update([
            'estado' => 'confirmada',
            'hora_checkin' => now(),
        ]);

        BitacoraService::registrar('CHECKIN_PACIENTE', 'P4_Citas', [
            'cita_id' => $cita->id,
            'codigo_ficha' => $cita->codigo_ficha,
            'paciente_id' => $cita->paciente_id,
            'hora_checkin' => now()->toTimeString(),
        ]);

        return back()->with('success', "Check-in registrado para {$cita->paciente->nombre_completo}. El paciente ha ingresado a sala de espera.");
    }

    /**
     * Inicia la atención en consultorio (CU10).
     */
    public function iniciarAtencion(Cita $cita)
    {
        $cita->update(['estado' => 'en_curso']);

        BitacoraService::registrar('INICIAR_CONSULTA', 'P4_Citas', [
            'cita_id' => $cita->id,
            'medico_id' => $cita->medico_id,
        ]);

        return back()->with('success', "Consulta iniciada en consultorio.");
    }

    /**
     * Finaliza la atención médica con observaciones/diagnóstico (CU10).
     */
    public function finalizarAtencion(Request $request, Cita $cita)
    {
        $validated = $request->validate([
            'observaciones_atencion' => 'nullable|string|max:1000',
        ]);

        $cita->update([
            'estado' => 'atendida',
            'observaciones_atencion' => $validated['observaciones_atencion'] ?? null,
        ]);

        BitacoraService::registrar('FINALIZAR_CONSULTA', 'P4_Citas', [
            'cita_id' => $cita->id,
            'paciente_id' => $cita->paciente_id,
            'medico_id' => $cita->medico_id,
        ]);

        return back()->with('success', "Atención médica concluida y registrada en la ficha clínica.");
    }

    /**
     * Registra inasistencia del paciente (CU10).
     */
    public function marcarInasistencia(Cita $cita)
    {
        $cita->update(['estado' => 'no_asistio']);

        BitacoraService::registrar('INASISTENCIA_PACIENTE', 'P4_Citas', [
            'cita_id' => $cita->id,
            'codigo_ficha' => $cita->codigo_ficha,
        ]);

        return back()->with('warning', "Se ha marcado la inasistencia del paciente para la cita {$cita->codigo_ficha}.");
    }

    /**
     * Cancela la cita médica con motivo obligatorio y libera el slot (CU11).
     */
    public function cancelar(Request $request, Cita $cita)
    {
        $validated = $request->validate([
            'motivo_cancelacion' => 'required|string|min:5|max:500',
        ], [
            'motivo_cancelacion.required' => 'Es obligatorio ingresar el motivo de cancelación de la cita.',
            'motivo_cancelacion.min' => 'El motivo de cancelación debe tener al menos 5 caracteres.',
        ]);

        if (in_array($cita->estado, ['atendida', 'cancelada'])) {
            return back()->with('error', 'No se puede cancelar una cita que ya fue atendida o cancelada previamente.');
        }

        DB::transaction(function () use ($cita, $validated) {
            // 1. Liberar el slot asociado para que vuelva a estar disponible
            if ($cita->slot) {
                $cita->slot->update(['estado' => 'disponible']);
            }

            // 2. Actualizar la cita a estado cancelada
            $cita->update([
                'estado' => 'cancelada',
                'motivo_cancelacion' => $validated['motivo_cancelacion'],
                'cancelado_por_user_id' => Auth::id(),
            ]);

            BitacoraService::registrar('CANCELAR_CITA', 'P4_Citas', [
                'cita_id' => $cita->id,
                'codigo_ficha' => $cita->codigo_ficha,
                'slot_liberado_id' => $cita->slot_id,
                'motivo' => $validated['motivo_cancelacion'],
            ]);
        });

        // Notificar cancelación por correo y aviso en sistema (CU16)
        \App\Services\NotificacionService::notificarCancelacion($cita, $validated['motivo_cancelacion']);

        return redirect()->route('citas.show', $cita)
            ->with('success', "La cita {$cita->codigo_ficha} ha sido cancelada y el cupo horario fue liberado inmediatamente.");
    }

    /**
     * Formulario interactivo para reprogramar la cita (CU11).
     */
    public function reprogramarView(Cita $cita)
    {
        $cita->load(['paciente', 'medico.user', 'medico.especialidad', 'slot']);

        return view('p4_citas.citas.reprogramar', [
            'cita' => $cita,
            'title' => "Reprogramar Cita: {$cita->codigo_ficha} — MediCita Pro",
            'header' => 'Reprogramación de Turno Médico',
        ]);
    }

    /**
     * Ejecuta la reprogramación de cita liberando el slot viejo y reservando el nuevo (CU11).
     */
    public function reprogramar(Request $request, Cita $cita)
    {
        $request->validate([
            'nuevo_slot_id' => 'required|exists:slots,id|different:slot_id',
            'motivo_reprogramacion' => 'required|string|max:500',
        ], [
            'nuevo_slot_id.required' => 'Debes seleccionar el nuevo turno para la cita.',
            'nuevo_slot_id.different' => 'Debes elegir un horario diferente al actual.',
            'motivo_reprogramacion.required' => 'El motivo de reprogramación es obligatorio.',
        ]);

        $nuevaCita = DB::transaction(function () use ($request, $cita) {
            $nuevoSlot = Slot::where('id', $request->nuevo_slot_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($nuevoSlot->estado !== 'disponible') {
                abort(422, 'El nuevo turno seleccionado ya no está disponible.');
            }

            // 1. Liberar el slot anterior
            if ($cita->slot) {
                $cita->slot->update(['estado' => 'disponible']);
            }

            // 2. Ocupar el nuevo slot
            $nuevoSlot->update(['estado' => 'reservado']);

            // 3. Crear o actualizar cita reprogramada
            $citaReprogramada = Cita::create([
                'codigo_ficha' => $cita->codigo_ficha . '-R',
                'paciente_id' => $cita->paciente_id,
                'medico_id' => $nuevoSlot->medico_id,
                'slot_id' => $nuevoSlot->id,
                'fecha' => $nuevoSlot->fecha,
                'hora' => $nuevoSlot->hora_inicio,
                'estado' => 'programada',
                'motivo_consulta' => $cita->motivo_consulta . " (Reprogramada: " . $request->motivo_reprogramacion . ")",
                'reprogramada_de_id' => $cita->id,
            ]);

            // Marcar cita previa como cancelada por reprogramación
            $cita->update([
                'estado' => 'cancelada',
                'motivo_cancelacion' => 'Reprogramada hacia ficha ' . $citaReprogramada->codigo_ficha . '. Motivo: ' . $request->motivo_reprogramacion,
                'cancelado_por_user_id' => Auth::id(),
            ]);

            BitacoraService::registrar('REPROGRAMAR_CITA', 'P4_Citas', [
                'cita_origen_id' => $cita->id,
                'cita_nueva_id' => $citaReprogramada->id,
                'nuevo_slot_id' => $nuevoSlot->id,
                'nueva_fecha' => $nuevoSlot->fecha,
                'nueva_hora' => $nuevoSlot->hora_inicio,
            ]);

            return $citaReprogramada;
        });

        // Notificar reprogramación por email y aviso en sistema (CU16)
        \App\Services\NotificacionService::notificarReprogramacion($nuevaCita, $cita);

        return redirect()->route('citas.show', $nuevaCita)
            ->with('success', "Cita reprogramada exitosamente. Se emitió la nueva ficha {$nuevaCita->codigo_ficha} para el día {$nuevaCita->fecha->format('d/m/Y')} a las {$nuevaCita->hora}.");
    }
}
