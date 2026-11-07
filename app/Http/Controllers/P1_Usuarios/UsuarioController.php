<?php

namespace App\Http\Controllers\P1_Usuarios;

use App\Http\Controllers\Controller;
use App\Models\P1\Rol;
use App\Models\P2\Especialidad;
use App\Models\P2\HorarioAtencion;
use App\Models\P2\Medico;
use App\Models\P3\HistoriaClinica;
use App\Models\P3\Paciente;
use App\Models\P3\ResponsableAutorizado;
use App\Models\User;
use App\Services\BitacoraService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UsuarioController extends Controller
{
    /**
     * Listado y filtros de usuarios del sistema (CU2).
     */
    public function index(Request $request): View
    {
        $query = User::with('rol')->latest();

        if ($request->filled('rol_id')) {
            $query->where('rol_id', $request->rol_id);
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('ci', 'like', "%{$search}%");
            });
        }

        $usuarios = $query->paginate(10)->withQueryString();
        $roles = Rol::where('activo', true)->get();

        $kpis = [
            'total' => User::count(),
            'activos' => User::where('estado', 'activo')->count(),
            'inactivos' => User::where('estado', 'inactivo')->count(),
            'bloqueados' => User::whereNotNull('bloqueado_hasta')->where('bloqueado_hasta', '>', now())->count(),
        ];

        return view('p1_auth.usuarios.index', [
            'usuarios' => $usuarios,
            'roles' => $roles,
            'kpis' => $kpis,
            'filters' => $request->all(),
            'title' => 'Gestión de Cuentas, Roles y Permisos (CU2) — MediCita Pro',
            'header' => 'Gestión de Usuarios y Roles',
        ]);
    }

    /**
     * Formulario para crear un nuevo usuario (CU2).
     */
    public function create(): View
    {
        $roles = Rol::where('activo', true)->get();
        $especialidades = Especialidad::where('activo', true)->orderBy('nombre')->get();

        return view('p1_auth.usuarios.create', [
            'roles' => $roles,
            'especialidades' => $especialidades,
            'title' => 'Registrar Usuario — MediCita Pro',
            'header' => 'Nuevo Usuario del Sistema',
        ]);
    }

    /**
     * Guarda un nuevo usuario en la base de datos (CU2).
     * Si se selecciona rol Paciente, genera su Ficha Clínica (CU7).
     * Si se selecciona rol Médico, genera su Perfil Médico y Horario Base (CU4).
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'rol_id' => 'required|exists:roles,id',
            'estado' => 'required|in:activo,inactivo',
        ], [
            'rol_id.required' => 'Debes asignar un rol al usuario.',
            'rol_id.exists' => 'El rol seleccionado no es válido.',
            'estado.required' => 'El estado inicial es obligatorio.',
        ]);

        $rol = Rol::findOrFail($request->integer('rol_id'));

        if ($rol->slug === 'paciente') {
            return $this->storePaciente($request, $rol);
        }

        if ($rol->slug === 'medico') {
            return $this->storeMedico($request, $rol);
        }

        return $this->storeGeneral($request, $rol);
    }

    /**
     * Crea un usuario con rol Paciente y su correspondiente ficha clínica (CU7).
     */
    protected function storePaciente(Request $request, Rol $rol): RedirectResponse
    {
        $validated = $request->validate([
            'ci' => 'required|string|max:20|unique:users,ci|unique:pacientes,ci',
            'nombres' => 'required|string|max:100',
            'apellidos' => 'required|string|max:100',
            'fecha_nacimiento' => 'required|date|before:today',
            'genero' => 'nullable|string|in:Masculino,Femenino,Otro',
            'grupo_sanguineo' => 'nullable|string|max:10',
            'telefono' => 'required|string|max:20',
            'email' => 'required|email|max:150|unique:users,email',
            'direccion' => 'nullable|string',
            'contacto_emergencia_nombre' => 'nullable|string|max:100',
            'contacto_emergencia_telefono' => 'nullable|string|max:20',
            'password' => ['nullable', 'string', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
            'estado' => 'required|in:activo,inactivo',
            'tiene_responsable' => 'nullable|boolean',
            'resp_ci' => 'required_if:tiene_responsable,1|nullable|string|max:20',
            'resp_nombres' => 'required_if:tiene_responsable,1|nullable|string|max:100',
            'resp_email' => 'required_if:tiene_responsable,1|nullable|email|max:150',
            'resp_parentesco' => 'required_if:tiene_responsable,1|nullable|string|max:50',
            'resp_telefono' => 'required_if:tiene_responsable,1|nullable|string|max:20',
            'resp_vigencia' => 'required_if:tiene_responsable,1|nullable|date|after:today',
        ], [
            'ci.required' => 'La Cédula de Identidad del paciente es obligatoria.',
            'ci.unique' => 'Ya existe un usuario o paciente registrado con este número de CI.',
            'nombres.required' => 'Los nombres del paciente son requeridos.',
            'apellidos.required' => 'Los apellidos del paciente son requeridos.',
            'fecha_nacimiento.required' => 'La fecha de nacimiento es obligatoria.',
            'fecha_nacimiento.before' => 'La fecha de nacimiento debe ser anterior al día de hoy.',
            'telefono.required' => 'El teléfono/celular de contacto es obligatorio.',
            'email.required' => 'El correo electrónico es obligatorio para el usuario del paciente.',
            'email.unique' => 'Este correo electrónico ya se encuentra registrado.',
            'resp_ci.required_if' => 'El CI del responsable legal es obligatorio.',
            'resp_email.required_if' => 'El correo electrónico del tutor es obligatorio para crear su cuenta de usuario.',
            'resp_email.email' => 'El formato del correo electrónico del tutor no es válido.',
            'resp_vigencia.after' => 'La fecha de vigencia de la autorización debe ser posterior a hoy.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
        ]);

        $nombreCompleto = trim($validated['nombres'].' '.$validated['apellidos']);
        $password = ! empty($validated['password']) ? $validated['password'] : $validated['ci'];
        $debeCambiar = empty($validated['password']);

        [$user, $paciente] = DB::transaction(function () use ($validated, $nombreCompleto, $password, $debeCambiar, $rol) {
            $user = User::create([
                'name' => $nombreCompleto,
                'ci' => $validated['ci'],
                'email' => $validated['email'],
                'password' => Hash::make($password),
                'rol_id' => $rol->id,
                'telefono' => $validated['telefono'],
                'estado' => $validated['estado'],
                'debe_cambiar_password' => $debeCambiar,
            ]);

            $paciente = Paciente::create([
                'user_id' => $user->id,
                'ci' => $validated['ci'],
                'nombres' => $validated['nombres'],
                'apellidos' => $validated['apellidos'],
                'fecha_nacimiento' => $validated['fecha_nacimiento'],
                'genero' => $validated['genero'] ?? null,
                'grupo_sanguineo' => $validated['grupo_sanguineo'] ?? null,
                'telefono' => $validated['telefono'],
                'email' => $validated['email'],
                'direccion' => $validated['direccion'] ?? null,
                'contacto_emergencia_nombre' => $validated['contacto_emergencia_nombre'] ?? null,
                'contacto_emergencia_telefono' => $validated['contacto_emergencia_telefono'] ?? null,
                'activo' => ($validated['estado'] === 'activo'),
            ]);

            HistoriaClinica::firstOrCreate(
                ['paciente_id' => $paciente->id],
                [
                    'nro_expediente' => 'HC-'.strtoupper(Str::random(8)),
                    'version' => 1,
                    'fecha_apertura' => now()->toDateString(),
                ]
            );

            if (! empty($validated['tiene_responsable']) && ! empty($validated['resp_nombres'])) {
                $tutorUser = User::where('ci', $validated['resp_ci'])->orWhere('email', $validated['resp_email'])->first();
                if (! $tutorUser) {
                    $tutorUser = User::create([
                        'name' => $validated['resp_nombres'],
                        'ci' => $validated['resp_ci'],
                        'email' => $validated['resp_email'],
                        'password' => Hash::make($validated['resp_ci']),
                        'rol_id' => $rol->id,
                        'telefono' => $validated['resp_telefono'],
                        'estado' => 'activo',
                        'debe_cambiar_password' => true,
                    ]);
                }

                ResponsableAutorizado::create([
                    'paciente_id' => $paciente->id,
                    'user_id' => $tutorUser->id,
                    'ci' => $validated['resp_ci'],
                    'nombres' => $validated['resp_nombres'],
                    'parentesco' => $validated['resp_parentesco'] ?? 'Tutor Legal',
                    'telefono' => $validated['resp_telefono'],
                    'vigencia_inicio' => today(),
                    'fecha_limite_vigencia' => $validated['resp_vigencia'] ?? now()->addYear()->toDateString(),
                    'puede_gestionar_citas' => true,
                    'puede_ver_resultados' => true,
                    'activo' => true,
                ]);
            }

            BitacoraService::registrar('CREAR_USUARIO', 'P1_Seguridad', [
                'usuario_creado_id' => $user->id,
                'email' => $user->email,
                'rol_id' => $user->rol_id,
            ]);

            BitacoraService::registrar('REGISTRAR_PACIENTE', 'P3_Pacientes', [
                'paciente_id' => $paciente->id,
                'usuario_id' => $user->id,
                'ci' => $paciente->ci,
                'nombre' => $paciente->nombre_completo,
                'email' => $user->email,
            ]);

            return [$user, $paciente];
        });

        return redirect()->route('usuarios.index')
            ->with('success', "Usuario Paciente '{$user->name}' registrado exitosamente con su Ficha Clínica Digital (CU7).");
    }

    /**
     * Crea un usuario con rol Médico y su correspondiente perfil profesional con horario base (CU4).
     */
    protected function storeMedico(Request $request, Rol $rol): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'ci' => 'required|string|max:20|unique:users,ci',
            'email' => 'required|email|max:150|unique:users,email',
            'telefono' => 'nullable|string|max:20',
            'password' => ['required', 'string', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
            'especialidad_id' => 'required|exists:especialidades,id',
            'nro_registro_medico' => 'required|string|max:50|unique:medicos,nro_registro_medico',
            'biografia' => 'nullable|string',
            'estado' => 'required|in:activo,inactivo',
        ], [
            'name.required' => 'El nombre completo del médico es obligatorio.',
            'ci.unique' => 'Ya existe un usuario con esta Cédula de Identidad.',
            'email.unique' => 'Este correo ya se encuentra registrado.',
            'nro_registro_medico.required' => 'La matrícula o número de registro médico es obligatorio.',
            'nro_registro_medico.unique' => 'Esta matrícula o número de registro médico ya existe.',
            'especialidad_id.required' => 'Debes asignar una especialidad al médico.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
        ]);

        [$user, $medico] = DB::transaction(function () use ($validated, $rol) {
            $user = User::create([
                'name' => $validated['name'],
                'ci' => $validated['ci'],
                'email' => $validated['email'],
                'telefono' => $validated['telefono'] ?? null,
                'password' => Hash::make($validated['password']),
                'rol_id' => $rol->id,
                'estado' => $validated['estado'],
            ]);

            $medico = Medico::create([
                'user_id' => $user->id,
                'especialidad_id' => $validated['especialidad_id'],
                'nro_registro_medico' => strtoupper($validated['nro_registro_medico']),
                'telefono_contacto' => $validated['telefono'] ?? null,
                'biografia' => $validated['biografia'] ?? null,
                'estado' => $validated['estado'],
            ]);

            for ($dia = 1; $dia <= 5; $dia++) {
                HorarioAtencion::create([
                    'medico_id' => $medico->id,
                    'dia_semana' => $dia,
                    'hora_inicio' => '08:00',
                    'hora_fin' => '17:00',
                    'duracion_slot_minutos' => 15,
                    'descanso_inicio' => '12:00',
                    'descanso_fin' => '13:00',
                    'activo' => true,
                ]);
            }

            BitacoraService::registrar('CREAR_USUARIO', 'P1_Seguridad', [
                'usuario_creado_id' => $user->id,
                'email' => $user->email,
                'rol_id' => $user->rol_id,
            ]);

            BitacoraService::registrar('REGISTRAR_MEDICO', 'P2_Medicos', [
                'medico_id' => $medico->id,
                'usuario_id' => $user->id,
                'nro_registro' => $medico->nro_registro_medico,
                'especialidad_id' => $medico->especialidad_id,
            ]);

            return [$user, $medico];
        });

        return redirect()->route('usuarios.index')
            ->with('success', "Usuario Médico '{$user->name}' registrado exitosamente con su perfil profesional y horario base (CU4).");
    }

    /**
     * Crea un usuario con rol general (Administrador, Recepcionista, Laboratorio).
     */
    protected function storeGeneral(Request $request, Rol $rol): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'ci' => 'required|string|max:20|unique:users,ci',
            'email' => 'required|email|max:150|unique:users,email',
            'telefono' => 'nullable|string|max:20',
            'password' => ['required', 'string', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
            'estado' => 'required|in:activo,inactivo',
        ], [
            'name.required' => 'El nombre completo es obligatorio.',
            'ci.unique' => 'Ya existe un usuario registrado con esta Cédula de Identidad.',
            'email.unique' => 'Este correo electrónico ya se encuentra registrado.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
        ]);

        $usuario = User::create([
            'name' => $validated['name'],
            'ci' => $validated['ci'],
            'email' => $validated['email'],
            'rol_id' => $rol->id,
            'telefono' => $validated['telefono'] ?? null,
            'password' => Hash::make($validated['password']),
            'estado' => $validated['estado'],
        ]);

        BitacoraService::registrar('CREAR_USUARIO', 'P1_Seguridad', [
            'usuario_creado_id' => $usuario->id,
            'email' => $usuario->email,
            'rol_id' => $usuario->rol_id,
        ]);

        return redirect()->route('usuarios.index')->with('success', "Usuario '{$usuario->name}' creado exitosamente.");
    }

    /**
     * Formulario para editar un usuario existente (CU2).
     */
    public function edit(User $usuario): View
    {
        $roles = Rol::where('activo', true)->get();

        return view('p1_auth.usuarios.edit', [
            'usuario' => $usuario,
            'roles' => $roles,
            'title' => "Editar Usuario: {$usuario->name} — MediCita Pro",
            'header' => 'Modificar Usuario',
        ]);
    }

    /**
     * Actualiza los datos de un usuario (CU2).
     */
    public function update(Request $request, User $usuario): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'ci' => ['required', 'string', 'max:20', Rule::unique('users', 'ci')->ignore($usuario->id)],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($usuario->id)],
            'rol_id' => 'required|exists:roles,id',
            'telefono' => 'nullable|string|max:20',
            'password' => ['nullable', 'string', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
            'estado' => 'required|in:activo,inactivo',
        ], [
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
        ]);

        $data = [
            'name' => $validated['name'],
            'ci' => $validated['ci'],
            'email' => $validated['email'],
            'rol_id' => $validated['rol_id'],
            'telefono' => $validated['telefono'] ?? null,
            'estado' => $validated['estado'],
        ];

        if (! empty($validated['password'])) {
            $data['password'] = Hash::make($validated['password']);
        }

        $usuario->update($data);

        BitacoraService::registrar('EDITAR_USUARIO', 'P1_Seguridad', [
            'usuario_id' => $usuario->id,
            'email' => $usuario->email,
        ]);

        return redirect()->route('usuarios.index')->with('success', "Datos de '{$usuario->name}' actualizados.");
    }

    /**
     * Alterna el estado activo / inactivo de un usuario (CU2).
     */
    public function toggleEstado(User $usuario): RedirectResponse
    {
        $nuevoEstado = $usuario->estado === 'activo' ? 'inactivo' : 'activo';
        $usuario->update(['estado' => $nuevoEstado]);

        BitacoraService::registrar('CAMBIAR_ESTADO_USUARIO', 'P1_Seguridad', [
            'usuario_id' => $usuario->id,
            'nuevo_estado' => $nuevoEstado,
        ]);

        return back()->with('success', "El usuario '{$usuario->name}' ahora está {$nuevoEstado}.");
    }
}
