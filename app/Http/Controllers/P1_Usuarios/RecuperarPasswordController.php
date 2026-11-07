<?php

namespace App\Http\Controllers\P1_Usuarios;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CorreoService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;
use Throwable;

class RecuperarPasswordController extends Controller
{
    public function create(): View
    {
        return view('p1_auth.recuperar-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email', 'max:255']]);
        try {
            CorreoService::verificarConfiguracion();
            Password::sendResetLink([...$credentials, 'estado' => 'activo']);
        } catch (Throwable $exception) {
            Log::error('Fallo de correo de recuperación', ['exception_type' => $exception::class]);

            return back()->withInput($request->only('email'))->withErrors([
                'email' => 'No pudimos enviar el correo. Inténtalo de nuevo en unos minutos.',
            ]);
        }

        return back()->with('success', 'Si el correo pertenece a una cuenta activa, recibirás un enlace para recuperar tu contraseña. Revisa también la carpeta de spam.');
    }

    public function edit(Request $request, string $token): View
    {
        return view('p1_auth.recuperar-password', ['token' => $token, 'email' => $request->query('email', '')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->mixedCase()->numbers()->symbols()],
        ]);
        $status = Password::reset([...$credentials, 'estado' => 'activo'], function (User $user, string $password): void {
            DB::transaction(function () use ($user, $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                    'debe_cambiar_password' => false,
                    'intentos_fallidos' => 0,
                    'bloqueado_hasta' => null,
                ])->save();
                DB::table('sessions')->where('user_id', $user->id)->delete();
            });
            event(new PasswordReset($user));
        });

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('success', 'Contraseña actualizada. Ya puedes iniciar sesión.');
        }

        return back()->withInput($request->only('email'))->withErrors([
            'email' => 'El enlace no es válido o ha caducado. Solicita uno nuevo.',
        ]);
    }
}
