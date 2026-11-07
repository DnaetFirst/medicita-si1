<?php

namespace App\Http\Controllers\P2_MedicosAgendas;

use App\Http\Controllers\Controller;
use App\Models\P2\BloqueoAgenda;
use App\Models\P2\Medico;
use App\Models\P2\Slot;
use App\Models\P4\Cita;
use App\Services\BitacoraService;
use App\Services\CitaWorkflowService;
use App\Services\NotificacionService;
use App\Services\SlotGeneratorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BloqueoController extends Controller
{
    /**
     * Listado y registro de bloqueos de agenda médica (CU6).
     */
    public function index(Request $request): View
    {
        $query = BloqueoAgenda::with(['medico.user', 'medico.especialidad', 'registradoPor'])->latest();

        if ($request->user()->isMedico()) {
            $query->where('medico_id', $request->user()->medico?->id ?? 0);
        }
        if ($request->filled('medico_id')) {
            $query->where('medico_id', $request->medico_id);
        }

        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        $bloqueos = $query->paginate(15)->withQueryString();
        $medicos = Medico::with('user')->where('estado', 'activo')->when($request->user()->isMedico(), fn ($q) => $q->whereKey($request->user()->medico?->id ?? 0))->get();

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
    public function store(Request $request): RedirectResponse
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

        abort_unless($request->user()->isAdmin() || ($request->user()->isMedico() && $request->user()->medico?->id === (int) $validated['medico_id'] && $request->user()->medico->estado === 'activo'), 403);
        DB::transaction(function () use ($request, $validated): void {
            Medico::whereKey($validated['medico_id'])->lockForUpdate()->firstOrFail();
            $bloqueo = BloqueoAgenda::create($validated + ['registrado_por_user_id' => $request->user()->id]);
            $generator = app(SlotGeneratorService::class);
            $workflow = app(CitaWorkflowService::class);
            $slots = Slot::where('medico_id', $bloqueo->medico_id)->whereDate('fecha', '>=', $bloqueo->fecha_inicio->toDateString())
                ->whereDate('fecha', '<=', $bloqueo->fecha_fin->toDateString())->lockForUpdate()->get();
            foreach ($slots as $slot) {
                if (! $generator->estaBloqueado($slot)) {
                    continue;
                }
                $cita = Cita::where('slot_id', $slot->id)->whereIn('estado', ['programada', 'confirmada', 'en_espera'])->lockForUpdate()->first();
                if ($cita) {
                    $anterior = clone $cita;
                    $cita->update(['estado' => 'postergada', 'version' => $cita->version + 1]);
                    $evento = $workflow->registrar($cita, 'POSTERGAR', $anterior, $bloqueo->motivo);
                    NotificacionService::registrarEvento($cita, $evento->operacion_id, 'postergada');
                }
                $generator->liberarSlot($slot);
            }
            BitacoraService::registrar('CREAR_BLOQUEO_AGENDA', 'P2_Medicos', ['bloqueo_id' => $bloqueo->id]);
            NotificacionService::notificarAdminBloqueo($bloqueo);
        }, 3);

        return redirect()->route('bloqueos.index')->with('success', 'Bloqueo registrado. Las citas pendientes quedaron postergadas; las atenciones iniciadas se conservan.');
    }

    /**
     * Elimina el bloqueo y restaura los slots a disponibles (CU6).
     */
    public function destroy(Request $request, BloqueoAgenda $bloqueo): RedirectResponse
    {
        abort_unless($request->user()->isAdmin() || ($request->user()->isMedico() && $request->user()->medico?->id === $bloqueo->medico_id && $request->user()->medico->estado === 'activo'), 403);
        DB::transaction(function () use ($bloqueo): void {
            Medico::whereKey($bloqueo->medico_id)->lockForUpdate()->firstOrFail();
            $actual = BloqueoAgenda::whereKey($bloqueo->id)->lockForUpdate()->firstOrFail();
            if ($actual->levantado_at) {
                return;
            }
            $actual->update(['levantado_at' => now()]);
            $generator = app(SlotGeneratorService::class);
            foreach (Slot::where('medico_id', $actual->medico_id)->whereDate('fecha', '>=', $actual->fecha_inicio->toDateString())->whereDate('fecha', '<=', $actual->fecha_fin->toDateString())->lockForUpdate()->get() as $slot) {
                $generator->liberarSlot($slot);
            }
            BitacoraService::registrar('LEVANTAR_BLOQUEO_AGENDA', 'P2_Medicos', ['bloqueo_id' => $actual->id]);
        }, 3);

        return redirect()->route('bloqueos.index')->with('success', 'Bloqueo levantado. Las citas postergadas requieren una reprogramación acordada.');
    }
}
