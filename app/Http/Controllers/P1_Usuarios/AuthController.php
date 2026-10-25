<?php

namespace App\Http\Controllers\P1_Usuarios;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\BitacoraService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /**
     * Muestra la pantalla de inicio de sesión (CU1).
     */
    public function showLoginForm()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('p1_auth.login', [
            'title' => 'Iniciar Sesión — MediCita Pro',
        ]);
    }

    /**
     * Procesa la solicitud de login con validación y protección contra fuerza bruta (CU1).
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ], [
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'Ingresa un correo electrónico válido.',
            'password.required' => 'La contraseña es obligatoria.',
        ]);

        $user = User::where('email', $credentials['email'])->first();

        // 1. Verificar si el usuario existe
        if (!$user) {
            BitacoraService::registrar('LOGIN_FALLIDO', 'P1_Seguridad', [
                'email' => $credentials['email'],
                'razon' => 'Usuario no encontrado',
            ]);

            return back()->withInput()->with('error', 'Credenciales inválidas. Verifica tu correo y contraseña.');
        }

        // 2. Verificar si la cuenta está inactiva
        if ($user->estado === 'inactivo') {
            BitacoraService::registrar('LOGIN_RECHAZADO', 'P1_Seguridad', [
                'user_id' => $user->id,
                'razon' => 'Cuenta inactiva',
            ]);

            return back()->with('error', 'Tu cuenta se encuentra desactivada. Contacta al administrador.');
        }

        // 3. Regla de negocio: Verificar bloqueo por fuerza bruta (3 intentos fallidos)
        if ($user->bloqueado_hasta && Carbon::parse($user->bloqueado_hasta)->isFuture()) {
            $minutosRestantes = Carbon::parse($user->bloqueado_hasta)->diffInMinutes(now()) + 1;

            BitacoraService::registrar('LOGIN_BLOQUEADO', 'P1_Seguridad', [
                'user_id' => $user->id,
                'minutos_restantes' => $minutosRestantes,
            ]);

            return back()->with('error', "Cuenta bloqueada por seguridad tras superar 3 intentos fallidos. Inténtalo de nuevo en {$minutosRestantes} minutos.");
        }

        // 4. Intentar autenticación
        $remember = $request->boolean('remember');

        if (Auth::attempt($credentials, $remember)) {
            // Resetear contador de intentos fallidos y bloqueo
            $user->update([
                'intentos_fallidos' => 0,
                'bloqueado_hasta' => null,
            ]);

            $request->session()->regenerate();

            // Registrar en auditoría (CU19)
            BitacoraService::registrar('LOGIN_EXITOSO', 'P1_Seguridad', [
                'rol' => $user->rol?->nombre,
                'ci' => $user->ci,
            ]);

            return redirect()->intended(route('dashboard'))
                ->with('success', "¡Bienvenido/a de nuevo, {$user->name}!");
        }

        // 5. Manejo de intento fallido
        $intentos = $user->intentos_fallidos + 1;
        $datosActualizar = ['intentos_fallidos' => $intentos];

        if ($intentos >= 3) {
            $datosActualizar['bloqueado_hasta'] = now()->addMinutes(15);
            $user->update($datosActualizar);

            BitacoraService::registrar('CUENTA_BLOQUEADA', 'P1_Seguridad', [
                'user_id' => $user->id,
                'email' => $user->email,
                'intentos' => $intentos,
                'duracion' => '15 minutos',
            ]);

            return back()->with('error', 'Has superado el límite de 3 intentos fallidos. Tu cuenta ha sido bloqueada temporalmente por 15 minutos.');
        }

        $user->update($datosActualizar);
        $intentosRestantes = 3 - $intentos;

        BitacoraService::registrar('LOGIN_PASSWORD_INCORRECTO', 'P1_Seguridad', [
            'user_id' => $user->id,
            'intentos_fallidos' => $intentos,
        ]);

        return back()->withInput()->with('error', "Contraseña incorrecta. Te quedan {$intentosRestantes} intento(s) antes del bloqueo temporal.");
    }

    /**
     * Cierra la sesión activa (CU1).
     */
    public function logout(Request $request)
    {
        $userId = Auth::id();
        $userName = Auth::user()?->name;

        BitacoraService::registrar('LOGOUT', 'P1_Seguridad', [
            'user_id' => $userId,
            'nombre' => $userName,
        ]);

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Sesión finalizada de manera segura.');
    }
}
