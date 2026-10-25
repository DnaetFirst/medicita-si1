<?php

namespace App\Http\Controllers\P2_MedicosAgendas;

use App\Http\Controllers\Controller;
use App\Models\P2\HorarioAtencion;
use App\Models\P2\Medico;
use App\Services\BitacoraService;
use App\Services\SlotGeneratorService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class HorarioController extends Controller
{
    /**
     * Resumen de horarios configurados por médico (CU5).
     */
    public function index(Request $request)
    {
        $medicos = Medico::with(['user', 'especialidad', 'horarios' => function ($q) {
            $q->where('activo', true)->orderBy('dia_semana');
        }])->where('estado', 'activo')->get();

        return view('p2_medicos.horarios.index', [
            'medicos' => $medicos,
            'title' => 'Gestión de Horarios y Slots (CU5) — MediCita Pro',
            'header' => 'Configuración de Horarios de Atención',
        ]);
    }

    /**
     * Formulario para configurar la matriz semanal de un médico (CU5).
     */
    public function edit(Medico $medico)
    {
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
     */
    public function update(Request $request, Medico $medico, SlotGeneratorService $slotGenerator)
    {
        $request->validate([
            'dias' => 'required|array',
        ]);

        foreach ($request->dias as $diaNum => $datos) {
            $activo = isset($datos['activo']);

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

        return redirect()->route('medicos.show', $medico)
            ->with('success', "Horario actualizado con éxito. Se generaron y sincronizaron {$totalSlots} slots de 15 minutos con 1 hora de descanso protegida.");
    }

    /**
     * Dispara la regeneración masiva de slots para las próximas 2 semanas (CU5).
     */
    public function generarSlots(Request $request, Medico $medico, SlotGeneratorService $slotGenerator)
    {
        $diasAdelante = $request->input('dias', 14);
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
