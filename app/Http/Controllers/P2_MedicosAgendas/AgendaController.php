<?php

namespace App\Http\Controllers\P2_MedicosAgendas;

use App\Http\Controllers\Controller;
use App\Models\P2\Medico;
use App\Models\P2\Slot;
use App\Services\SlotGeneratorService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AgendaController extends Controller
{
    /**
     * Vista de consulta interactiva de agenda médica (CU12).
     */
    public function show(Request $request, SlotGeneratorService $slotGenerator)
    {
        $user = Auth::user();
        $medicos = Medico::with(['user', 'especialidad'])->where('estado', 'activo')->get();

        // 1. Determinar el médico seleccionado
        if ($user->isMedico()) {
            $medicoSeleccionado = $user->medico;
        } elseif ($request->filled('medico_id')) {
            $medicoSeleccionado = Medico::with(['user', 'especialidad'])->find($request->medico_id);
        } else {
            $medicoSeleccionado = $medicos->first();
        }

        // 2. Determinar la fecha de consulta
        $fecha = $request->input('fecha', today()->toDateString());
        $fechaCarbon = Carbon::parse($fecha);

        // 3. Generar o recuperar slots para el día
        $slots = collect();
        if ($medicoSeleccionado) {
            $slots = $slotGenerator->generarSlotsParaFecha($medicoSeleccionado, $fecha);
        }

        // 4. Estadísticas del día
        $stats = [
            'total' => $slots->count(),
            'disponibles' => $slots->where('estado', 'disponible')->count(),
            'reservados' => $slots->where('estado', 'reservado')->count(),
            'descanso' => $slots->where('estado', 'descanso')->count(),
            'bloqueados' => $slots->where('estado', 'bloqueado')->count(),
        ];

        return view('p2_medicos.agenda.show', [
            'medicos' => $medicos,
            'medico' => $medicoSeleccionado,
            'fecha' => $fecha,
            'fechaCarbon' => $fechaCarbon,
            'slots' => $slots,
            'stats' => $stats,
            'title' => 'Consulta de Agenda Médica (CU12) — MediCita Pro',
            'header' => 'Agenda Médica y Turnos',
        ]);
    }

    /**
     * Endpoint API para obtener slots en formato JSON (para wizards y calendario reactivo).
     */
    public function apiSlots(Request $request, SlotGeneratorService $slotGenerator)
    {
        $request->validate([
            'medico_id' => 'required|exists:medicos,id',
            'fecha' => 'required|date',
        ]);

        $medico = Medico::findOrFail($request->medico_id);
        $slots = $slotGenerator->generarSlotsParaFecha($medico, $request->fecha);

        $stats = [
            'total' => $slots->count(),
            'disponibles' => $slots->where('estado', 'disponible')->count(),
            'reservados' => $slots->where('estado', 'reservado')->count(),
            'descanso' => $slots->where('estado', 'descanso')->count(),
            'bloqueados' => $slots->where('estado', 'bloqueado')->count(),
        ];

        return response()->json([
            'success' => true,
            'medico' => $medico->user?->name,
            'fecha' => $request->fecha,
            'slots' => $slots,
            'stats' => $stats,
        ]);
    }
}
