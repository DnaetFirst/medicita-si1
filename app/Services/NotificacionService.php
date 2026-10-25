<?php

namespace App\Services;

use App\Mail\CitaCanceladaMail;
use App\Mail\CitaConfirmadaMail;
use App\Mail\CitaReprogramadaMail;
use App\Models\P4\Cita;
use App\Models\P7\Notificacion;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificacionService
{
    /**
     * Envía notificación de cita confirmada al paciente y registra aviso interno (CU16).
     */
    public static function notificarConfirmacion(Cita $cita): void
    {
        $cita->loadMissing(['paciente.user', 'medico.user', 'medico.especialidad']);
        $emailDestino = $cita->paciente?->email ?? $cita->paciente?->user?->email;

        // 1. Enviar correo electrónico si tiene dirección válida
        if ($emailDestino) {
            try {
                Mail::to($emailDestino)->send(new CitaConfirmadaMail($cita));
            } catch (\Throwable $e) {
                Log::warning("No se pudo enviar email de confirmación para cita {$cita->codigo_ficha}: " . $e->getMessage());
            }
        }

        // 2. Registrar notificación interna en base de datos (CU16)
        $userId = $cita->paciente?->user_id ?? $cita->medico?->user_id;
        if ($userId) {
            Notificacion::create([
                'user_id' => $userId,
                'tipo' => 'cita_confirmada',
                'titulo' => "Ficha Confirmada: {$cita->codigo_ficha}",
                'mensaje' => "Tu cita para {$cita->medico?->especialidad?->nombre} con el Dr./Dra. {$cita->medico?->user?->name} fue confirmada para el {$cita->fecha->format('d/m/Y')} a las " . substr($cita->hora, 0, 5) . " hrs.",
                'leido' => false,
            ]);
        }

        BitacoraService::registrar('NOTIFICAR_CITA_CONFIRMADA', 'P7_Notificaciones', [
            'cita_id' => $cita->id,
            'codigo_ficha' => $cita->codigo_ficha,
            'destinatario' => $emailDestino ?? 'Sin email',
        ]);
    }

    /**
     * Envía notificación de reprogramación de turno (CU16).
     */
    public static function notificarReprogramacion(Cita $citaNueva, Cita $citaAnterior): void
    {
        $citaNueva->loadMissing(['paciente.user', 'medico.user', 'medico.especialidad']);
        $emailDestino = $citaNueva->paciente?->email ?? $citaNueva->paciente?->user?->email;

        if ($emailDestino) {
            try {
                Mail::to($emailDestino)->send(new CitaReprogramadaMail($citaNueva, $citaAnterior));
            } catch (\Throwable $e) {
                Log::warning("No se pudo enviar email de reprogramación para cita {$citaNueva->codigo_ficha}: " . $e->getMessage());
            }
        }

        $userId = $citaNueva->paciente?->user_id ?? $citaNueva->medico?->user_id;
        if ($userId) {
            Notificacion::create([
                'user_id' => $userId,
                'tipo' => 'reprogramacion',
                'titulo' => "Cita Reprogramada: {$citaNueva->codigo_ficha}",
                'mensaje' => "Tu turno anterior fue modificado para el {$citaNueva->fecha->format('d/m/Y')} a las " . substr($citaNueva->hora, 0, 5) . " hrs.",
                'leido' => false,
            ]);
        }

        BitacoraService::registrar('NOTIFICAR_CITA_REPROGRAMADA', 'P7_Notificaciones', [
            'cita_nueva_id' => $citaNueva->id,
            'cita_anterior_id' => $citaAnterior->id,
            'destinatario' => $emailDestino ?? 'Sin email',
        ]);
    }

    /**
     * Envía aviso de cancelación de turno con motivo (CU16).
     */
    public static function notificarCancelacion(Cita $cita, string $motivo): void
    {
        $cita->loadMissing(['paciente.user', 'medico.user', 'medico.especialidad']);
        $emailDestino = $cita->paciente?->email ?? $cita->paciente?->user?->email;

        if ($emailDestino) {
            try {
                Mail::to($emailDestino)->send(new CitaCanceladaMail($cita, $motivo));
            } catch (\Throwable $e) {
                Log::warning("No se pudo enviar email de cancelación para cita {$cita->codigo_ficha}: " . $e->getMessage());
            }
        }

        $userId = $cita->paciente?->user_id ?? $cita->medico?->user_id;
        if ($userId) {
            Notificacion::create([
                'user_id' => $userId,
                'tipo' => 'cita_cancelada',
                'titulo' => "Cita Cancelada: {$cita->codigo_ficha}",
                'mensaje' => "Tu cita para {$cita->fecha->format('d/m/Y')} fue cancelada. Motivo: {$motivo}",
                'leido' => false,
            ]);
        }

        BitacoraService::registrar('NOTIFICAR_CITA_CANCELADA', 'P7_Notificaciones', [
            'cita_id' => $cita->id,
            'codigo_ficha' => $cita->codigo_ficha,
            'motivo' => $motivo,
            'destinatario' => $emailDestino ?? 'Sin email',
        ]);
    }
}
