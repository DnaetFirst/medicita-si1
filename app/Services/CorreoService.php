<?php

namespace App\Services;

use RuntimeException;

class CorreoService
{
    public static function verificarConfiguracion(): void
    {
        if (app()->environment('production') && in_array(config('mail.mailers.'.config('mail.default').'.transport'), ['log', 'array'], true)) {
            throw new RuntimeException('El correo de producción requiere un transporte de entrega real.');
        }
    }
}
