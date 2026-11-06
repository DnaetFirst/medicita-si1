<?php

namespace App\Services;

use App\Jobs\EnviarAviso;
use App\Models\P4\Cita;
use App\Models\P7\Notificacion;
use App\Models\User;

class NotificacionService
{
    public static function registrarEvento(Cita $cita, string $eventoId, string $tipo): void
    {
        $destinatarios = collect([$cita->paciente->user_id]);
        $destinatarios = $destinatarios->merge($cita->paciente->responsables()
            ->where('activo', true)->where('puede_gestionar_citas', true)
            ->whereDate('fecha_limite_vigencia', '>=', today())
            ->where(fn ($q) => $q->whereNull('vigencia_inicio')->orWhereDate('vigencia_inicio', '<=', today()))->pluck('user_id'));
        if ($tipo === 'postergada') {
            $destinatarios = $destinatarios->merge(User::whereHas('rol', fn ($q) => $q->where('slug', 'recepcionista'))->pluck('id'));
        }
        foreach ($destinatarios->filter()->unique() as $userId) {
            $aviso = Notificacion::firstOrCreate(['evento_id' => $eventoId.':'.$userId], [
                'user_id' => $userId, 'cita_id' => $cita->id, 'tipo' => $tipo,
                'titulo' => match ($tipo) {
                    'reserva' => 'Cita programada', 'cancelar' => 'Cita cancelada', 'postergada' => 'Atención postergada', default => 'Cita reprogramada'
                },
                'mensaje' => 'Ficha '.$cita->codigo_ficha.'. Fecha: '.$cita->fecha->format('d/m/Y').' '.substr($cita->hora, 0, 5).'. Consulta los detalles en el portal.',
                'leido' => false,
            ]);
            if ($aviso->wasRecentlyCreated) {
                EnviarAviso::dispatch($aviso->id)->afterCommit();
            }
        }
    }
}
