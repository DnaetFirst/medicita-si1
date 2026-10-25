<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!Auth::check()) {
            return redirect()->route('login')->with('error', 'Debes iniciar sesión para acceder.');
        }

        $user = Auth::user();
        $userRole = $user->rol?->slug;

        // Superadministrador y Administrador tienen acceso total irrestricto
        if (in_array($userRole, ['admin', 'superadmin'])) {
            return $next($request);
        }

        if (!in_array($userRole, $roles)) {
            abort(403, 'No tienes permisos para acceder a este módulo.');
        }

        return $next($request);
    }
}
