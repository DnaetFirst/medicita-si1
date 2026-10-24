<?php

namespace App\Services;

use App\Models\P1\Bitacora;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class BitacoraService
{
    public static function registrar(string $accion, string $modulo, $detalles = null): Bitacora
    {
        $user = Auth::user();

        return Bitacora::create([
            'user_id' => $user?->id,
            'usuario_nombre' => $user?->name ?? 'Sistema / Anónimo',
            'rol' => $user?->rol?->nombre ?? 'Público',
            'accion' => $accion,
            'modulo' => $modulo,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'detalles' => is_array($detalles) ? json_encode($detalles, JSON_UNESCAPED_UNICODE) : (string)$detalles,
            'created_at' => now(),
        ]);
    }
}
