<?php

namespace App\Http\Controllers\P3_Pacientes;

use App\Http\Controllers\Controller;
use App\Models\P1\Rol;
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
use Illuminate\View\View;

class PacienteController extends Controller
{
    /**
     * Listado general de pacientes con filtros y métricas (CU7).
     */
    public function index(Request $request): View
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
            'con_responsable' => Paciente::whereHas('responsables', fn ($q) => $q->where('activo', true))->count(),
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
    public function create(): View
    {
        return view('p3_pacientes.pacientes.create', [
            'title' => 'Registrar Paciente — MediCita Pro',
            'header' => 'Nueva Ficha de Paciente',
        ]);
    }

    /**
     * Guarda el paciente, su responsable autorizado opcional, y crea su cuenta de acceso PAC (CU7 + CU2).
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ci' => 'required|string|max:20|unique:pacientes,ci',
            'nombres' => 'required|string|max:100',
            'apellidos' => 'required|string|max:100',
            'fecha_nacimiento' => 'required|date|before:today',
            'genero' => 'nullable|string|in:Masculino,Femenino,Otro',
            'grupo_sanguineo' => 'nullable|string|max:10',
            'telefono' => 'required|string|max:20',
            'email' => 'nullable|email|max:150|unique:users,email',
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
            'email.unique' => 'Este correo ya está registrado para otro usuario del sistema.',
            'resp_ci.required_if' => 'El CI del responsable legal es obligatorio.',
            'resp_vigencia.after' => 'La fecha de vigencia de la autorización debe ser posterior a hoy.',
        ]);

        // Obtener el rol PAC
        $rolPaciente = Rol::where('slug', 'paciente')->first();

        // Contraseña inicial: Número de CI (carnet de identidad) para todo paciente
        $passwordInicial = $validated['ci'];

        // Determinar email de acceso
        $emailAcceso = $validated['email'] ?? null;
        $emailGenerado = false;
        if (! $emailAcceso) {
            $emailAcceso = 'pac.'.$validated['ci'].'@medicita.local';
            $emailGenerado = true;
        }

        [$paciente, $usuario] = DB::transaction(function () use ($validated, $emailAcceso, $passwordInicial, $rolPaciente) {
            // 1. Crear o recuperar cuenta de acceso PAC con CI como contraseña inicial
            $usuario = User::where('ci', $validated['ci'])->orWhere('email', $emailAcceso)->first();
            if (! $usuario) {
                $usuario = User::create([
                    'name' => trim($validated['nombres'].' '.$validated['apellidos']),
                    'ci' => $validated['ci'],
                    'email' => $emailAcceso,
                    'password' => Hash::make($passwordInicial),
                    'rol_id' => $rolPaciente?->id,
                    'telefono' => $validated['telefono'],
                    'estado' => 'activo',
                    'debe_cambiar_password' => true,
                ]);
            } else {
                $usuario->update([
                    'rol_id' => $rolPaciente?->id ?? $usuario->rol_id,
                    'estado' => 'activo',
                ]);
            }

            // 2. Crear ficha clínica y vincular user_id (CU7)
            $paciente = Paciente::create([
                'user_id' => $usuario->id,
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

            // 3. Crear historia clínica base
            HistoriaClinica::firstOrCreate(
                ['paciente_id' => $paciente->id],
                [
                    'nro_expediente' => 'HC-'.strtoupper(Str::random(8)),
                    'version' => 1,
                    'fecha_apertura' => now()->toDateString(),
                ]
            );

            // 4. Responsable legal / tutor opcional
            if (! empty($validated['tiene_responsable']) && ! empty($validated['resp_nombres'])) {
                $tutorUser = User::where('ci', $validated['resp_ci'])->first();

                ResponsableAutorizado::create([
                    'paciente_id' => $paciente->id,
                    'user_id' => $tutorUser?->id,
                    'ci' => $validated['resp_ci'],
                    'nombres' => $validated['resp_nombres'],
                    'parentesco' => $validated['resp_parentesco'],
                    'telefono' => $validated['resp_telefono'],
                    'vigencia_inicio' => today(),
                    'fecha_limite_vigencia' => $validated['resp_vigencia'],
                    'puede_gestionar_citas' => true,
                    'puede_ver_resultados' => true,
                    'activo' => true,
                ]);
            }

            return [$paciente, $usuario];
        });

        BitacoraService::registrar('REGISTRAR_PACIENTE', 'P3_Pacientes', [
            'paciente_id' => $paciente->id,
            'usuario_id' => $usuario->id,
            'ci' => $paciente->ci,
            'nombre' => $paciente->nombre_completo,
            'email' => $emailAcceso,
        ]);

        $mensajePassword = "Paciente {$paciente->nombre_completo} registrado con cuenta vinculada de acceso al portal.<br>"
            ."Identificador de acceso: <strong>{$emailAcceso}</strong> (o carnet: <strong>{$validated['ci']}</strong>)<br>"
            ."Contraseña inicial: <strong>{$passwordInicial}</strong> (número de CI, se solicitará cambio por seguridad en el primer inicio de sesión).";

        return redirect()->route('pacientes.show', $paciente)
            ->with('success_html', $mensajePassword);
    }

    /**
     * Muestra la ficha clínica integral del paciente (CU7).
     */
    public function show(Paciente $paciente): View
    {
        $paciente->load([
            'responsables' => fn ($q) => $q->latest(),
            'citas' => fn ($q) => $q->with(['medico.user', 'medico.especialidad'])->latest('fecha'),
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
    public function edit(Paciente $paciente): View
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
    public function update(Request $request, Paciente $paciente): RedirectResponse
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
    public function toggleEstado(Paciente $paciente): RedirectResponse
    {
        $paciente->activo = ! $paciente->activo;
        $paciente->save();

        BitacoraService::registrar('TOGGLE_ESTADO_PACIENTE', 'P3_Pacientes', [
            'paciente_id' => $paciente->id,
            'nuevo_estado' => $paciente->activo ? 'activo' : 'inactivo',
        ]);

        return back()->with('success', 'Estado del paciente actualizado a '.($paciente->activo ? 'Activo' : 'Inactivo'));
    }

    /**
     * Agrega un responsable legal/autorizado al paciente (CU7).
     */
    public function storeResponsable(Request $request, Paciente $paciente): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => 'nullable|integer|exists:users,id',
            'vigencia_inicio' => 'nullable|date|before_or_equal:fecha_limite_vigencia',
            'puede_gestionar_citas' => 'sometimes|boolean',
            'puede_ver_resultados' => 'sometimes|boolean',
            'ci' => 'required|string|max:20',
            'nombres' => 'required|string|max:100',
            'parentesco' => 'required|string|max:50',
            'telefono' => 'required|string|max:20',
            'fecha_limite_vigencia' => 'required|date|after:today',
        ], [
            'fecha_limite_vigencia.after' => 'La fecha límite de vigencia debe ser posterior a la fecha actual.',
        ]);

        $tutorUserId = $validated['user_id'] ?? null;
        if (! empty($tutorUserId)) {
            $cuenta = User::findOrFail($tutorUserId);
            abort_unless($cuenta->isPaciente() && $cuenta->estado === 'activo' && $cuenta->ci === $validated['ci'], 422, 'La cuenta debe ser de paciente, estar activa y corresponder al CI del responsable.');
        } else {
            // Vincular automáticamente si ya existe una cuenta de usuario para este CI de tutor
            $cuentaExistente = User::where('ci', $validated['ci'])->first();
            if ($cuentaExistente) {
                $tutorUserId = $cuentaExistente->id;
            } else {
                // Crear cuenta para el tutor con su CI como contraseña inicial
                $rolPaciente = Rol::where('slug', 'paciente')->first();
                $nuevoTutorUser = User::create([
                    'name' => $validated['nombres'],
                    'ci' => $validated['ci'],
                    'email' => 'tutor.'.$validated['ci'].'@medicita.local',
                    'password' => Hash::make($validated['ci']),
                    'rol_id' => $rolPaciente?->id,
                    'telefono' => $validated['telefono'],
                    'estado' => 'activo',
                    'debe_cambiar_password' => true,
                ]);
                $tutorUserId = $nuevoTutorUser->id;
            }
        }

        $puedeGestionar = $request->has('puede_gestionar_citas') ? $request->boolean('puede_gestionar_citas') : true;
        $puedeVer = $request->has('puede_ver_resultados') ? $request->boolean('puede_ver_resultados') : true;

        $responsable = $paciente->responsables()->create([
            'user_id' => $tutorUserId,
            'vigencia_inicio' => $validated['vigencia_inicio'] ?? today(),
            'puede_gestionar_citas' => $puedeGestionar,
            'puede_ver_resultados' => $puedeVer,
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
            'tutor_user_id' => $tutorUserId,
            'nombres' => $responsable->nombres,
            'vigencia' => $responsable->fecha_limite_vigencia->toDateString(),
        ]);

        return back()->with('success', 'Responsable legal/autorizado acreditado correctamente. El tutor puede gestionar citas y ver resultados desde su portal.');
    }

    /**
     * Desactiva un responsable autorizado garantizando que el paciente no pierda ningún dato ni quede sin usuario (CU7).
     */
    public function destroyResponsable(Paciente $paciente, ResponsableAutorizado $responsable): RedirectResponse
    {
        abort_unless($responsable->paciente_id === $paciente->id, 404);

        $responsable->activo = false;
        $responsable->save();

        // Garantizar que el paciente siempre cuente con su usuario PAC activo para gestionar sus datos autónomamente
        if (! $paciente->user_id || ! $paciente->user) {
            $rolPaciente = Rol::where('slug', 'paciente')->first();
            $email = ! empty($paciente->email) ? $paciente->email : 'pac.'.$paciente->ci.'@medicita.local';
            $usuario = User::firstOrCreate(
                ['ci' => $paciente->ci],
                [
                    'name' => $paciente->nombre_completo,
                    'email' => $email,
                    'password' => Hash::make($paciente->ci),
                    'rol_id' => $rolPaciente?->id,
                    'telefono' => $paciente->telefono,
                    'estado' => 'activo',
                    'debe_cambiar_password' => true,
                ]
            );
            $paciente->update(['user_id' => $usuario->id]);
        } else {
            $paciente->user->update(['estado' => 'activo']);
        }

        BitacoraService::registrar('DESACTIVAR_RESPONSABLE_AUTORIZADO', 'P3_Pacientes', [
            'paciente_id' => $paciente->id,
            'responsable_id' => $responsable->id,
            'detalle' => 'Tutoría revocada. Ficha clínica, citas y exámenes preservados íntegramente en la cuenta del paciente.',
        ]);

        return back()->with('success', 'La tutoría ha sido revocada. Todos los datos, citas y resultados continúan íntegros y preservados en la cuenta del paciente.');
    }
}
