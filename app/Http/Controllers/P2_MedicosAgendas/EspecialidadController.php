<?php

namespace App\Http\Controllers\P2_MedicosAgendas;

use App\Http\Controllers\Controller;
use App\Models\P2\Especialidad;
use App\Services\BitacoraService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EspecialidadController extends Controller
{
    /**
     * Catálogo y gestión de especialidades médicas (CU3).
     */
    public function index()
    {
        $especialidades = Especialidad::withCount('medicos')->get();

        return view('p2_medicos.especialidades.index', [
            'especialidades' => $especialidades,
            'title' => 'Gestión de Especialidades (CU3) — MediCita Pro',
            'header' => 'Catálogo de Especialidades Médicas',
        ]);
    }

    /**
     * Registra una nueva especialidad médica (CU3).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:100|unique:especialidades,nombre',
            'codigo' => 'required|string|max:20|unique:especialidades,codigo',
            'descripcion' => 'nullable|string',
            'color' => 'required|string|max:20',
            'icono' => 'nullable|string|max:50',
        ], [
            'nombre.unique' => 'Ya existe una especialidad con este nombre.',
            'codigo.unique' => 'Este código de especialidad ya se encuentra registrado.',
            'color.required' => 'Debes asignar un color identificativo a la especialidad.',
        ]);

        $especialidad = Especialidad::create([
            'nombre' => $validated['nombre'],
            'codigo' => strtoupper($validated['codigo']),
            'descripcion' => $validated['descripcion'],
            'color' => $validated['color'],
            'icono' => $validated['icono'] ?? 'stethoscope',
            'activo' => true,
        ]);

        BitacoraService::registrar('CREAR_ESPECIALIDAD', 'P2_Medicos', [
            'especialidad_id' => $especialidad->id,
            'nombre' => $especialidad->nombre,
            'codigo' => $especialidad->codigo,
        ]);

        return back()->with('success', "Especialidad '{$especialidad->nombre}' registrada con éxito.");
    }

    /**
     * Actualiza una especialidad médica existente (CU3).
     */
    public function update(Request $request, Especialidad $especialidad)
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:100', Rule::unique('especialidades', 'nombre')->ignore($especialidad->id)],
            'codigo' => ['required', 'string', 'max:20', Rule::unique('especialidades', 'codigo')->ignore($especialidad->id)],
            'descripcion' => 'nullable|string',
            'color' => 'required|string|max:20',
            'icono' => 'nullable|string|max:50',
        ]);

        $especialidad->update([
            'nombre' => $validated['nombre'],
            'codigo' => strtoupper($validated['codigo']),
            'descripcion' => $validated['descripcion'],
            'color' => $validated['color'],
            'icono' => $validated['icono'] ?? $especialidad->icono,
        ]);

        BitacoraService::registrar('EDITAR_ESPECIALIDAD', 'P2_Medicos', [
            'especialidad_id' => $especialidad->id,
            'nombre' => $especialidad->nombre,
        ]);

        return back()->with('success', "Especialidad '{$especialidad->nombre}' actualizada correctamente.");
    }

    /**
     * Alterna el estado activo / inactivo (CU3).
     */
    public function toggleEstado(Especialidad $especialidad)
    {
        $nuevoEstado = !$especialidad->activo;
        $especialidad->update(['activo' => $nuevoEstado]);

        $texto = $nuevoEstado ? 'activada' : 'desactivada';

        BitacoraService::registrar('CAMBIAR_ESTADO_ESPECIALIDAD', 'P2_Medicos', [
            'especialidad_id' => $especialidad->id,
            'activo' => $nuevoEstado,
        ]);

        return back()->with('success', "La especialidad '{$especialidad->nombre}' ha sido {$texto}.");
    }

    /**
     * Elimina una especialidad si no tiene médicos vinculados (CU3).
     */
    public function destroy(Especialidad $especialidad)
    {
        if ($especialidad->medicos()->count() > 0) {
            return back()->with('error', "No se puede eliminar la especialidad '{$especialidad->nombre}' porque tiene {$especialidad->medicos()->count()} médico(s) asignado(s). Puedes desactivarla en su lugar.");
        }

        $nombre = $especialidad->nombre;
        $especialidad->delete();

        BitacoraService::registrar('ELIMINAR_ESPECIALIDAD', 'P2_Medicos', [
            'nombre' => $nombre,
        ]);

        return back()->with('success', "Especialidad '{$nombre}' eliminada permanentemente.");
    }
}
