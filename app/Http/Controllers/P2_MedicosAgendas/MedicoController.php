<?php

namespace App\Http\Controllers\P2_MedicosAgendas;

use App\Http\Controllers\Controller;
use App\Models\P1\Rol;
use App\Models\P2\Especialidad;
use App\Models\P2\HorarioAtencion;
use App\Models\P2\Medico;
use App\Models\User;
use App\Services\BitacoraService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MedicoController extends Controller
{
    /**
     * Listado general y filtros del catálogo de médicos (CU4).
     */
    public function index(Request $request): View
    {
        $query = Medico::with(['user', 'especialidad'])->latest();

        if ($request->filled('especialidad_id')) {
            $query->where('especialidad_id', $request->especialidad_id);
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nro_registro_medico', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($qu) use ($search) {
                        $qu->where('name', 'like', "%{$search}%")
                            ->orWhere('ci', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $medicos = $query->paginate(10)->withQueryString();
        $especialidades = Especialidad::where('activo', true)->get();

        $kpis = [
            'total' => Medico::count(),
            'activos' => Medico::where('estado', 'activo')->count(),
            'inactivos' => Medico::where('estado', 'inactivo')->count(),
            'especialidades_cubiertas' => Medico::distinct('especialidad_id')->count('especialidad_id'),
        ];

        return view('p2_medicos.medicos.index', [
            'medicos' => $medicos,
            'especialidades' => $especialidades,
            'kpis' => $kpis,
            'filters' => $request->all(),
            'title' => 'Gestión de Médicos (CU4) — MediCita Pro',
            'header' => 'Catálogo del Personal Médico',
        ]);
    }

    /**
     * Formulario para registrar un nuevo médico (CU4).
     */
    public function create(): View
    {
        $especialidades = Especialidad::where('activo', true)->get();

        return view('p2_medicos.medicos.create', [
            'especialidades' => $especialidades,
            'title' => 'Registrar Médico — MediCita Pro',
            'header' => 'Nuevo Registro Médico',
        ]);
    }

    /**
     * Guarda el nuevo médico y su usuario asociado (CU4).
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'ci' => 'required|string|max:20|unique:users,ci',
            'email' => 'required|email|max:150|unique:users,email',
            'telefono' => 'nullable|string|max:20',
            'password' => 'required|string|min:8|confirmed',
            'especialidad_id' => 'required|exists:especialidades,id',
            'nro_registro_medico' => 'required|string|max:50|unique:medicos,nro_registro_medico',
            'biografia' => 'nullable|string',
            'estado' => 'required|in:activo,inactivo',
        ], [
            'name.required' => 'El nombre completo es obligatorio.',
            'ci.unique' => 'Ya existe un usuario con esta Cédula de Identidad.',
            'email.unique' => 'Este correo ya se encuentra registrado.',
            'nro_registro_medico.unique' => 'Esta matrícula o número de registro médico ya existe.',
            'especialidad_id.required' => 'Debes asignar una especialidad al médico.',
        ]);

        $rolMedico = Rol::where('slug', 'medico')->firstOrFail();

        // 1. Crear cuenta de usuario con rol Médico
        $user = User::create([
            'name' => $validated['name'],
            'ci' => $validated['ci'],
            'email' => $validated['email'],
            'telefono' => $validated['telefono'] ?? null,
            'password' => Hash::make($validated['password']),
            'rol_id' => $rolMedico->id,
            'estado' => $validated['estado'],
        ]);

        // 2. Crear perfil médico
        $medico = Medico::create([
            'user_id' => $user->id,
            'especialidad_id' => $validated['especialidad_id'],
            'nro_registro_medico' => strtoupper($validated['nro_registro_medico']),
            'telefono_contacto' => $validated['telefono'] ?? null,
            'biografia' => $validated['biografia'] ?? null,
            'estado' => $validated['estado'],
        ]);

        // 3. Crear horario base semanal por defecto (Lunes a Viernes 08:00–17:00, descanso 12:00–13:00)
        for ($dia = 1; $dia <= 5; $dia++) {
            HorarioAtencion::create([
                'medico_id' => $medico->id,
                'dia_semana' => $dia,
                'hora_inicio' => '08:00',
                'hora_fin' => '17:00',
                'duracion_slot_minutos' => 15,
                'descanso_inicio' => '12:00',
                'descanso_fin' => '13:00', // 1 hora exacta de descanso
                'activo' => true,
            ]);
        }

        BitacoraService::registrar('REGISTRAR_MEDICO', 'P2_Medicos', [
            'medico_id' => $medico->id,
            'usuario_id' => $user->id,
            'matricula' => $medico->nro_registro_medico,
            'especialidad_id' => $medico->especialidad_id,
        ]);

        return redirect()->route('medicos.index')->with('success', "Médico '{$user->name}' registrado correctamente con su agenda base semanal.");
    }

    /**
     * Ficha detallada del médico (CU4).
     */
    public function show(Medico $medico): View
    {
        $medico->load(['user', 'especialidad', 'horarios' => function ($q) {
            $q->orderBy('dia_semana');
        }, 'citas' => function ($q) {
            $q->whereDate('fecha', '>=', today())->orderBy('fecha')->orderBy('hora')->take(5);
        }]);

        return view('p2_medicos.medicos.show', [
            'medico' => $medico,
            'title' => "Perfil: {$medico->user->name} — MediCita Pro",
            'header' => 'Ficha del Médico',
        ]);
    }

    /**
     * Formulario de edición del médico (CU4).
     */
    public function edit(Medico $medico): View
    {
        $especialidades = Especialidad::where('activo', true)->get();

        return view('p2_medicos.medicos.edit', [
            'medico' => $medico,
            'especialidades' => $especialidades,
            'title' => "Editar: {$medico->user->name} — MediCita Pro",
            'header' => 'Modificar Datos del Médico',
        ]);
    }

    /**
     * Actualiza el perfil del médico y usuario asociado (CU4).
     */
    public function update(Request $request, Medico $medico): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'ci' => ['required', 'string', 'max:20', Rule::unique('users', 'ci')->ignore($medico->user_id)],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($medico->user_id)],
            'telefono' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:8|confirmed',
            'especialidad_id' => 'required|exists:especialidades,id',
            'nro_registro_medico' => ['required', 'string', 'max:50', Rule::unique('medicos', 'nro_registro_medico')->ignore($medico->id)],
            'biografia' => 'nullable|string',
            'estado' => 'required|in:activo,inactivo,de_baja',
        ]);

        $userData = [
            'name' => $validated['name'],
            'ci' => $validated['ci'],
            'email' => $validated['email'],
            'telefono' => $validated['telefono'] ?? null,
            'estado' => $validated['estado'] === 'activo' ? 'activo' : 'inactivo',
        ];

        if (! empty($validated['password'])) {
            $userData['password'] = Hash::make($validated['password']);
        }

        $medico->user->update($userData);

        $medico->update([
            'especialidad_id' => $validated['especialidad_id'],
            'nro_registro_medico' => strtoupper($validated['nro_registro_medico']),
            'telefono_contacto' => $validated['telefono'] ?? null,
            'biografia' => $validated['biografia'] ?? null,
            'estado' => $validated['estado'],
        ]);

        BitacoraService::registrar('EDITAR_MEDICO', 'P2_Medicos', [
            'medico_id' => $medico->id,
            'matricula' => $medico->nro_registro_medico,
        ]);

        return redirect()->route('medicos.index')->with('success', "Datos del médico '{$medico->user->name}' actualizados.");
    }

    /**
     * Alterna el estado del médico (CU4).
     */
    public function toggleEstado(Medico $medico): RedirectResponse
    {
        $nuevoEstado = $medico->estado === 'activo' ? 'inactivo' : 'activo';
        $medico->update(['estado' => $nuevoEstado]);
        $medico->user->update(['estado' => $nuevoEstado]);

        BitacoraService::registrar('CAMBIAR_ESTADO_MEDICO', 'P2_Medicos', [
            'medico_id' => $medico->id,
            'nuevo_estado' => $nuevoEstado,
        ]);

        return back()->with('success', "El médico '{$medico->user->name}' ha cambiado a estado {$nuevoEstado}.");
    }
}
