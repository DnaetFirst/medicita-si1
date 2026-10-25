<?php

namespace App\Http\Controllers\P3_Pacientes;

use App\Http\Controllers\Controller;
use App\Models\P3\Paciente;
use App\Models\P3\ResponsableAutorizado;
use App\Services\BitacoraService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PacienteController extends Controller
{
    /**
     * Listado general de pacientes con filtros y métricas (CU7).
     */
    public function index(Request $request)
    {
        $query = Paciente::with(['responsables' => function ($q) {
            $q->where('activo', true);
        }])->latest();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nombres', 'like', "%{$search}%")
                  ->orWhere('apellidos', 'like', "%{$search}%")
                  ->orWhere('ci', 'like', "%{$search}%")
                  ->orWhere('telefono', 'like', "%{$search}%");
            });
        }

        if ($request->filled('estado')) {
            $query->where('activo', $request->estado === 'activo');
        }

        $pacientes = $query->paginate(12)->withQueryString();

        $kpis = [
            'total' => Paciente::count(),
            'activos' => Paciente::where('activo', true)->count(),
            'con_responsable' => Paciente::whereHas('responsables', fn($q) => $q->where('activo', true))->count(),
            'nuevos_mes' => Paciente::whereMonth('created_at', now()->month)->count(),
        ];

        return view('p3_pacientes.pacientes.index', [
            'pacientes' => $pacientes,
            'kpis' => $kpis,
            'filters' => $request->all(),
            'title' => 'Gestión de Pacientes (CU7) — MediCita Pro',
            'header' => 'Registro y Fichas de Pacientes',
        ]);
    }

    /**
     * Formulario para registrar un nuevo paciente (CU7).
     */
    public function create()
    {
        return view('p3_pacientes.pacientes.create', [
            'title' => 'Registrar Paciente — MediCita Pro',
            'header' => 'Nueva Ficha de Paciente',
        ]);
    }

    /**
     * Guarda el paciente y su responsable autorizado opcional (CU7).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'ci' => 'required|string|max:20|unique:pacientes,ci',
            'nombres' => 'required|string|max:100',
            'apellidos' => 'required|string|max:100',
            'fecha_nacimiento' => 'required|date|before:today',
            'genero' => 'nullable|string|in:Masculino,Femenino,Otro',
            'grupo_sanguineo' => 'nullable|string|max:10',
            'telefono' => 'required|string|max:20',
            'email' => 'nullable|email|max:150',
            'direccion' => 'nullable|string',
            'contacto_emergencia_nombre' => 'nullable|string|max:100',
            'contacto_emergencia_telefono' => 'nullable|string|max:20',

            // Responsable legal opcional
            'tiene_responsable' => 'nullable|boolean',
            'resp_ci' => 'required_if:tiene_responsable,1|nullable|string|max:20',
            'resp_nombres' => 'required_if:tiene_responsable,1|nullable|string|max:100',
            'resp_parentesco' => 'required_if:tiene_responsable,1|nullable|string|max:50',
            'resp_telefono' => 'required_if:tiene_responsable,1|nullable|string|max:20',
            'resp_vigencia' => 'required_if:tiene_responsable,1|nullable|date|after:today',
        ], [
            'ci.required' => 'La Cédula de Identidad del paciente es obligatoria.',
            'ci.unique' => 'Ya existe un paciente registrado con este número de CI.',
            'nombres.required' => 'Los nombres del paciente son requeridos.',
            'apellidos.required' => 'Los apellidos del paciente son requeridos.',
            'fecha_nacimiento.required' => 'La fecha de nacimiento es obligatoria.',
            'fecha_nacimiento.before' => 'La fecha de nacimiento debe ser anterior al día de hoy.',
            'telefono.required' => 'El número de teléfono/celular de contacto es obligatorio.',
            'resp_ci.required_if' => 'El CI del responsable legal es obligatorio.',
            'resp_vigencia.after' => 'La fecha de vigencia de la autorización debe ser posterior a hoy.',
        ]);

        $paciente = Paciente::create([
            'ci' => $validated['ci'],
            'nombres' => $validated['nombres'],
            'apellidos' => $validated['apellidos'],
            'fecha_nacimiento' => $validated['fecha_nacimiento'],
            'genero' => $validated['genero'] ?? null,
            'grupo_sanguineo' => $validated['grupo_sanguineo'] ?? null,
            'telefono' => $validated['telefono'],
            'email' => $validated['email'] ?? null,
            'direccion' => $validated['direccion'] ?? null,
            'contacto_emergencia_nombre' => $validated['contacto_emergencia_nombre'] ?? null,
            'contacto_emergencia_telefono' => $validated['contacto_emergencia_telefono'] ?? null,
            'activo' => true,
        ]);

        if (!empty($validated['tiene_responsable']) && !empty($validated['resp_nombres'])) {
            ResponsableAutorizado::create([
                'paciente_id' => $paciente->id,
                'ci' => $validated['resp_ci'],
                'nombres' => $validated['resp_nombres'],
                'parentesco' => $validated['resp_parentesco'],
                'telefono' => $validated['resp_telefono'],
                'fecha_limite_vigencia' => $validated['resp_vigencia'],
                'activo' => true,
            ]);
        }

        BitacoraService::registrar('REGISTRAR_PACIENTE', 'P3_Pacientes', [
            'paciente_id' => $paciente->id,
            'ci' => $paciente->ci,
            'nombre' => $paciente->nombre_completo,
        ]);

        return redirect()->route('pacientes.show', $paciente)
            ->with('success', "Paciente {$paciente->nombre_completo} registrado exitosamente.");
    }

    /**
     * Muestra la ficha clínica integral del paciente (CU7).
     */
    public function show(Paciente $paciente)
    {
        $paciente->load([
            'responsables' => fn($q) => $q->latest(),
            'citas' => fn($q) => $q->with(['medico.user', 'medico.especialidad'])->latest('fecha'),
        ]);

        return view('p3_pacientes.pacientes.show', [
            'paciente' => $paciente,
            'title' => "Ficha: {$paciente->nombre_completo} — MediCita Pro",
            'header' => 'Ficha Integral del Paciente',
        ]);
    }

    /**
     * Formulario de edición del paciente (CU7).
     */
    public function edit(Paciente $paciente)
    {
        return view('p3_pacientes.pacientes.edit', [
            'paciente' => $paciente,
            'title' => "Editar Paciente: {$paciente->nombre_completo} — MediCita Pro",
            'header' => 'Modificar Datos del Paciente',
        ]);
    }

    /**
     * Actualiza los datos del paciente (CU7).
     */
    public function update(Request $request, Paciente $paciente)
    {
        $validated = $request->validate([
            'ci' => ['required', 'string', 'max:20', Rule::unique('pacientes', 'ci')->ignore($paciente->id)],
            'nombres' => 'required|string|max:100',
            'apellidos' => 'required|string|max:100',
            'fecha_nacimiento' => 'required|date|before:today',
            'genero' => 'nullable|string|in:Masculino,Femenino,Otro',
            'grupo_sanguineo' => 'nullable|string|max:10',
            'telefono' => 'required|string|max:20',
            'email' => 'nullable|email|max:150',
            'direccion' => 'nullable|string',
            'contacto_emergencia_nombre' => 'nullable|string|max:100',
            'contacto_emergencia_telefono' => 'nullable|string|max:20',
            'activo' => 'required|boolean',
        ]);

        $paciente->update($validated);

        BitacoraService::registrar('ACTUALIZAR_PACIENTE', 'P3_Pacientes', [
            'paciente_id' => $paciente->id,
            'ci' => $paciente->ci,
        ]);

        return redirect()->route('pacientes.show', $paciente)
            ->with('success', 'Ficha del paciente actualizada con éxito.');
    }

    /**
     * Activa o desactiva un paciente (CU7).
     */
    public function toggleEstado(Paciente $paciente)
    {
        $paciente->activo = !$paciente->activo;
        $paciente->save();

        BitacoraService::registrar('TOGGLE_ESTADO_PACIENTE', 'P3_Pacientes', [
            'paciente_id' => $paciente->id,
            'nuevo_estado' => $paciente->activo ? 'activo' : 'inactivo',
        ]);

        return back()->with('success', "Estado del paciente actualizado a " . ($paciente->activo ? 'Activo' : 'Inactivo'));
    }

    /**
     * Agrega un responsable legal/autorizado al paciente (CU7).
     */
    public function storeResponsable(Request $request, Paciente $paciente)
    {
        $validated = $request->validate([
            'ci' => 'required|string|max:20',
            'nombres' => 'required|string|max:100',
            'parentesco' => 'required|string|max:50',
            'telefono' => 'required|string|max:20',
            'fecha_limite_vigencia' => 'required|date|after:today',
        ], [
            'fecha_limite_vigencia.after' => 'La fecha límite de vigencia debe ser posterior a la fecha actual.',
        ]);

        $responsable = $paciente->responsables()->create([
            'ci' => $validated['ci'],
            'nombres' => $validated['nombres'],
            'parentesco' => $validated['parentesco'],
            'telefono' => $validated['telefono'],
            'fecha_limite_vigencia' => $validated['fecha_limite_vigencia'],
            'activo' => true,
        ]);

        BitacoraService::registrar('REGISTRAR_RESPONSABLE_AUTORIZADO', 'P3_Pacientes', [
            'paciente_id' => $paciente->id,
            'responsable_id' => $responsable->id,
            'nombres' => $responsable->nombres,
            'vigencia' => $responsable->fecha_limite_vigencia->toDateString(),
        ]);

        return back()->with('success', 'Responsable legal/autorizado registrado correctamente con vigencia verificada.');
    }

    /**
     * Desactiva un responsable autorizado (CU7).
     */
    public function destroyResponsable(Paciente $paciente, ResponsableAutorizado $responsable)
    {
        $responsable->activo = false;
        $responsable->save();

        BitacoraService::registrar('DESACTIVAR_RESPONSABLE_AUTORIZADO', 'P3_Pacientes', [
            'paciente_id' => $paciente->id,
            'responsable_id' => $responsable->id,
        ]);

        return back()->with('success', 'La autorización del responsable ha sido revocada.');
    }
}
