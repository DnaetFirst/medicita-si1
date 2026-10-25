<?php

namespace App\Http\Controllers\P2_MedicosAgendas;

use App\Http\Controllers\Controller;
use App\Models\P2\BloqueoAgenda;
use App\Models\P2\Medico;
use App\Models\P2\Slot;
use App\Services\BitacoraService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BloqueoController extends Controller
{
    /**
     * Listado y registro de bloqueos de agenda médica (CU6).
     */
    public function index(Request $request)
    {
        $query = BloqueoAgenda::with(['medico.user', 'medico.especialidad', 'registradoPor'])->latest();

        if ($request->filled('medico_id')) {
            $query->where('medico_id', $request->medico_id);
        }

        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        $bloqueos = $query->paginate(15)->withQueryString();
        $medicos = Medico::with('user')->where('estado', 'activo')->get();

        return view('p2_medicos.bloqueos.index', [
            'bloqueos' => $bloqueos,
            'medicos' => $medicos,
            'filters' => $request->all(),
            'title' => 'Bloqueos de Agenda y Postergaciones (CU6) — MediCita Pro',
            'header' => 'Gestión de Bloqueos de Agenda',
        ]);
    }

    /**
     * Registra un bloqueo de agenda y deshabilita los slots involucrados (CU6).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'medico_id' => 'required|exists:medicos,id',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            'tipo' => 'required|in:feriado,permiso,capacitacion,emergencia',
            'motivo' => 'required|string|max:255',
        ], [
            'medico_id.required' => 'Debes seleccionar el médico afectado.',
            'fecha_fin.after_or_equal' => 'La fecha de fin debe ser posterior o igual a la fecha de inicio.',
            'motivo.required' => 'El motivo del bloqueo es obligatorio.',
        ]);

        $bloqueo = BloqueoAgenda::create([
            'medico_id' => $validated['medico_id'],
            'fecha_inicio' => $validated['fecha_inicio'],
            'fecha_fin' => $validated['fecha_fin'],
            'tipo' => $validated['tipo'],
            'motivo' => $validated['motivo'],
            'registrado_por_user_id' => Auth::id(),
        ]);

        // Inhabilitar inmediatamente los slots disponibles en ese rango de fechas
        $ini = Carbon::parse($validated['fecha_inicio'])->toDateString();
        $fin = Carbon::parse($validated['fecha_fin'])->toDateString();

        $slotsAfectados = Slot::where('medico_id', $validated['medico_id'])
            ->whereDate('fecha', '>=', $ini)
            ->whereDate('fecha', '<=', $fin)
            ->where('estado', 'disponible')
            ->update(['estado' => 'bloqueado']);

        BitacoraService::registrar('CREAR_BLOQUEO_AGENDA', 'P2_Medicos', [
            'bloqueo_id' => $bloqueo->id,
            'medico_id' => $bloqueo->medico_id,
            'tipo' => $bloqueo->tipo,
            'motivo' => $bloqueo->motivo,
            'slots_inhabilitados' => $slotsAfectados,
        ]);

        return redirect()->route('bloqueos.index')->with('success', "Bloqueo registrado con éxito. Se inhabilitaron {$slotsAfectados} slots disponibles para el período.");
    }

    /**
     * Elimina el bloqueo y restaura los slots a disponibles (CU6).
     */
    public function destroy(BloqueoAgenda $bloqueo)
    {
        $ini = Carbon::parse($bloqueo->fecha_inicio)->toDateString();
        $fin = Carbon::parse($bloqueo->fecha_fin)->toDateString();
        $medicoId = $bloqueo->medico_id;

        // Restaurar slots bloqueados a disponible (salvo que coincidan con descanso)
        Slot::where('medico_id', $medicoId)
            ->whereDate('fecha', '>=', $ini)
            ->whereDate('fecha', '<=', $fin)
            ->where('estado', 'bloqueado')
            ->update(['estado' => 'disponible']);

        $motivo = $bloqueo->motivo;
        $bloqueo->delete();

        BitacoraService::registrar('ELIMINAR_BLOQUEO_AGENDA', 'P2_Medicos', [
            'medico_id' => $medicoId,
            'motivo' => $motivo,
        ]);

        return redirect()->route('bloqueos.index')->with('success', "Bloqueo levantado correctamente. Los turnos han vuelto a estar disponibles.");
    }
}
