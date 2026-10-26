<?php

namespace App\Http\Controllers\P1_Usuarios;

use App\Http\Controllers\Controller;
use App\Models\P1\Rol;
use App\Models\User;
use App\Services\BitacoraService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
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

        return view('p1_auth.usuarios.create', [
            'roles' => $roles,
            'title' => 'Registrar Usuario — MediCita Pro',
            'header' => 'Nuevo Usuario del Sistema',
        ]);
    }

    /**
     * Guarda un nuevo usuario en la base de datos (CU2).
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'ci' => 'required|string|max:20|unique:users,ci',
            'email' => 'required|email|max:150|unique:users,email',
            'rol_id' => 'required|exists:roles,id',
            'telefono' => 'nullable|string|max:20',
            'password' => 'required|string|min:8|confirmed',
            'estado' => 'required|in:activo,inactivo',
        ], [
            'name.required' => 'El nombre completo es obligatorio.',
            'ci.unique' => 'Ya existe un usuario registrado con esta Cédula de Identidad.',
            'email.unique' => 'Este correo electrónico ya se encuentra registrado.',
            'rol_id.required' => 'Debes asignar un rol al usuario.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
        ]);

        $usuario = User::create([
            'name' => $validated['name'],
            'ci' => $validated['ci'],
            'email' => $validated['email'],
            'rol_id' => $validated['rol_id'],
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
            'password' => 'nullable|string|min:8|confirmed',
            'estado' => 'required|in:activo,inactivo',
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
