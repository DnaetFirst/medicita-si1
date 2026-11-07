<?php

namespace App\Http\Controllers\P2_MedicosAgendas;

use App\Http\Controllers\Controller;
use App\Models\P2\HorarioAtencion;
use App\Models\P2\Medico;
use App\Models\P4\Cita;
use App\Services\BitacoraService;
use App\Services\SlotGeneratorService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class HorarioController extends Controller
{
    /**
     * Resumen de horarios configurados por médico (CU5).
     * El médico opera solo sobre su propia agenda; el administrador y la recepción pueden ver todos.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $query = Medico::with(['user', 'especialidad', 'horarios' => function ($q) {
            $q->where('activo', true)->orderBy('dia_semana');
        }])->where('estado', 'activo');

        if ($user->isMedico()) {
            $query->where('user_id', $user->id);
        }

        $medicos = $query->get();

        return view('p2_medicos.horarios.index', [
            'medicos' => $medicos,
            'title' => $user->isMedico() ? 'Mis Horarios y Slots (CU5) — MediCita Pro' : 'Gestión de Horarios y Slots (CU5) — MediCita Pro',
            'header' => $user->isMedico() ? 'Configuración de Mi Horario de Atención' : 'Configuración de Horarios de Atención',
        ]);
    }

    /**
     * Formulario para configurar la matriz semanal de un médico (CU5).
     * El médico opera solo sobre su propia agenda.
     */
    public function edit(Request $request, Medico $medico): View
    {
        $user = $request->user();
        if ($user->isMedico()) {
            abort_unless($medico->user_id === $user->id, 403, 'No tienes autorización para gestionar los horarios de otro médico.');
        }

        $medico->load(['user', 'especialidad']);
        $horariosExistentes = HorarioAtencion::where('medico_id', $medico->id)->get()->keyBy('dia_semana');

        $dias = [
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
            7 => 'Domingo',
        ];

        return view('p2_medicos.horarios.edit', [
            'medico' => $medico,
            'dias' => $dias,
            'horariosExistentes' => $horariosExistentes,
            'title' => "Configurar Horario: {$medico->user?->name} — MediCita Pro",
            'header' => 'Gestión de Agenda Semanal y Descanso',
        ]);
    }

    /**
     * Guarda la configuración de la semana para el médico (CU5).
     * Reglas:
     * - Slots fijos de 15 min con 1 hora de descanso excluido de turnos.
     * - No se superponen horarios entre especialidades del mismo médico.
     * - Los cambios incompatibles con citas pendientes se rechazan; no se desplazan citas.
     */
    public function update(Request $request, Medico $medico, SlotGeneratorService $slotGenerator): RedirectResponse
    {
        $user = $request->user();
        if ($user->isMedico()) {
            abort_unless($medico->user_id === $user->id, 403, 'No tienes autorización para gestionar los horarios de otro médico.');
        }

        $request->validate([
            'dias' => 'required|array:1,2,3,4,5,6,7',
            'dias.*' => 'array',
            'dias.*.activo' => 'sometimes|boolean',
            'dias.*.hora_inicio' => 'required_if:dias.*.activo,1|date_format:H:i',
            'dias.*.hora_fin' => 'required_if:dias.*.activo,1|date_format:H:i|after:dias.*.hora_inicio',
            'dias.*.descanso_inicio' => 'required_if:dias.*.activo,1|date_format:H:i|after_or_equal:dias.*.hora_inicio',
        ]);

        $diasNombres = [
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
            7 => 'Domingo',
        ];

        // 1. Validar que el descanso obligatorio de 1 hora esté contenido dentro de la jornada laboral
        foreach ($request->dias as $diaNum => $datos) {
            if (! empty($datos['activo'])) {
                $descansoFin = Carbon::parse($datos['descanso_inicio'])->addHour();
                if ($descansoFin->gt(Carbon::parse($datos['hora_fin']))) {
                    throw ValidationException::withMessages([
                        "dias.$diaNum.descanso_inicio" => 'La hora de descanso (1 hora completa) debe estar dentro de la jornada.',
                    ]);
                }
            }
        }

        // 2. REGLA DE NEGOCIO: No se superponen entre especialidades del mismo médico
        $otrosMedicos = Medico::where('user_id', $medico->user_id)
            ->where('id', '!=', $medico->id)
            ->where('estado', 'activo')
            ->with(['especialidad', 'horarios' => function ($q) {
                $q->where('activo', true);
            }])
            ->get();

        if ($otrosMedicos->isNotEmpty()) {
            foreach ($request->dias as $diaNum => $datos) {
                if (empty($datos['activo'])) {
                    continue;
                }

                $horaInicio = $datos['hora_inicio'] ?? '08:00';
                $horaFin = $datos['hora_fin'] ?? '17:00';

                foreach ($otrosMedicos as $otro) {
                    $otroHorario = $otro->horarios->firstWhere('dia_semana', (int) $diaNum);
                    if ($otroHorario && $otroHorario->activo) {
                        $otroIni = substr($otroHorario->hora_inicio, 0, 5);
                        $otroFin = substr($otroHorario->hora_fin, 0, 5);

                        // Comprobar superposición de rangos [horaInicio, horaFin) y [otroIni, otroFin)
                        if ($horaInicio < $otroFin && $horaFin > $otroIni) {
                            $nombreDia = $diasNombres[(int) $diaNum] ?? "Día $diaNum";
                            $espNombre = $otro->especialidad?->nombre ?? 'otra especialidad';
                            throw ValidationException::withMessages([
                                "dias.$diaNum.hora_inicio" => "Conflicto de agenda: El horario del {$nombreDia} ({$horaInicio} - {$horaFin}) se superpone con la jornada de {$espNombre} ({$otroIni} - {$otroFin}) del mismo médico.",
                            ]);
                        }
                    }
                }
            }
        }

        // 3. REGLA DE NEGOCIO: Los cambios incompatibles con citas pendientes se rechazan; no se desplazan citas
        $citasPendientes = Cita::where('medico_id', $medico->id)
            ->whereDate('fecha', '>=', today())
            ->whereIn('estado', ['programada', 'confirmada', 'en_espera', 'en_atencion'])
            ->get();

        foreach ($citasPendientes as $cita) {
            $diaSemanaCita = Carbon::parse($cita->fecha)->dayOfWeekIso;
            $nombreDia = $diasNombres[$diaSemanaCita] ?? "Día $diaSemanaCita";
            $fechaCitaStr = Carbon::parse($cita->fecha)->format('d/m/Y');
            $horaCita = substr($cita->hora, 0, 5);
            $horaCitaFin = Carbon::parse($cita->hora)->addMinutes(15)->format('H:i');

            $diaConfig = $request->dias[$diaSemanaCita] ?? null;
            $activo = ! empty($diaConfig['activo']);

            // Si el día fue desactivado y hay una cita pendiente
            if (! $activo) {
                throw ValidationException::withMessages([
                    "dias.$diaSemanaCita.activo" => "Incompatibilidad: No se puede desactivar el {$nombreDia}. El médico tiene citas pendientes programadas (Ficha {$cita->codigo_ficha} el {$fechaCitaStr} a las {$horaCita}). Las citas no se pueden desplazar.",
                ]);
            }

            // Si el día está activo, verificar si la cita queda fuera del rango o dentro del descanso
            $horaInicio = $diaConfig['hora_inicio'] ?? '08:00';
            $horaFin = $diaConfig['hora_fin'] ?? '17:00';
            $descansoInicio = $diaConfig['descanso_inicio'] ?? '12:00';
            $descansoFin = Carbon::parse($descansoInicio)->addHour()->format('H:i');

            if ($horaCita < $horaInicio || $horaCitaFin > $horaFin) {
                throw ValidationException::withMessages([
                    "dias.$diaSemanaCita.hora_inicio" => "Incompatibilidad: La cita pendiente {$cita->codigo_ficha} del {$fechaCitaStr} a las {$horaCita} queda fuera del nuevo rango laboral ({$horaInicio} - {$horaFin}). Los cambios incompatibles se rechazan; no se desplazan citas.",
                ]);
            }

            if ($horaCita < $descansoFin && $horaCitaFin > $descansoInicio) {
                throw ValidationException::withMessages([
                    "dias.$diaSemanaCita.descanso_inicio" => "Incompatibilidad: La cita pendiente {$cita->codigo_ficha} del {$fechaCitaStr} a las {$horaCita} coincide con el nuevo horario de descanso ({$descansoInicio} - {$descansoFin}). Los cambios incompatibles se rechazan; no se desplazan citas.",
                ]);
            }
        }

        // 4. Guardar configuración de días
        foreach ($request->dias as $diaNum => $datos) {
            $activo = ! empty($datos['activo']);

            if ($activo) {
                $horaInicio = $datos['hora_inicio'] ?? '08:00';
                $horaFin = $datos['hora_fin'] ?? '17:00';
                $descansoInicio = $datos['descanso_inicio'] ?? '12:00';

                // REGLA DE NEGOCIO CRÍTICA: Descanso fijo de 1 hora exacta (4 slots de 15 min)
                $descansoFin = Carbon::parse($descansoInicio)->addHour()->format('H:i');

                HorarioAtencion::updateOrCreate(
                    [
                        'medico_id' => $medico->id,
                        'dia_semana' => $diaNum,
                    ],
                    [
                        'hora_inicio' => $horaInicio,
                        'hora_fin' => $horaFin,
                        'duracion_slot_minutos' => 15, // Fija en 15 min según requerimiento
                        'descanso_inicio' => $descansoInicio,
                        'descanso_fin' => $descansoFin,
                        'activo' => true,
                    ]
                );
            } else {
                HorarioAtencion::where('medico_id', $medico->id)
                    ->where('dia_semana', $diaNum)
                    ->update(['activo' => false]);
            }
        }

        // Generar slots automáticamente para los próximos 7 días hábiles
        $totalSlots = 0;
        for ($i = 0; $i < 7; $i++) {
            $fecha = now()->addDays($i)->toDateString();
            $slots = $slotGenerator->generarSlotsParaFecha($medico, $fecha);
            $totalSlots += $slots->count();
        }

        BitacoraService::registrar('CONFIGURAR_HORARIO', 'P2_Medicos', [
            'medico_id' => $medico->id,
            'slots_generados' => $totalSlots,
        ]);

        $redirectRoute = $user->isMedico() ? route('horarios.edit', $medico) : route('medicos.show', $medico);

        return redirect($redirectRoute)
            ->with('success', "Horario actualizado con éxito. Se generaron y sincronizaron {$totalSlots} slots de 15 minutos con 1 hora de descanso protegida.");
    }

    /**
     * Dispara la regeneración masiva de slots para las próximas 2 semanas (CU5).
     * El médico opera solo sobre su propia agenda.
     */
    public function generarSlots(Request $request, Medico $medico, SlotGeneratorService $slotGenerator): RedirectResponse
    {
        $user = $request->user();
        if ($user->isMedico()) {
            abort_unless($medico->user_id === $user->id, 403, 'No tienes autorización para gestionar los horarios de otro médico.');
        }

        $request->validate(['dias' => 'sometimes|integer|min:1|max:90']);
        $diasAdelante = $request->integer('dias', 14);
        $totalSlots = 0;

        for ($i = 0; $i < $diasAdelante; $i++) {
            $fecha = now()->addDays($i)->toDateString();
            $slots = $slotGenerator->generarSlotsParaFecha($medico, $fecha);
            $totalSlots += $slots->count();
        }

        BitacoraService::registrar('GENERAR_SLOTS', 'P2_Medicos', [
            'medico_id' => $medico->id,
            'dias' => $diasAdelante,
            'total_slots' => $totalSlots,
        ]);

        return back()->with('success', "Se han generado y actualizado {$totalSlots} slots de 15 minutos para los próximos {$diasAdelante} días.");
    }
}
