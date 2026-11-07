<?php

namespace App\Services;

use App\Jobs\EnviarAviso;
use App\Models\P2\BloqueoAgenda;
use App\Models\P4\Cita;
use App\Models\P4\OfertaCupo;
use App\Models\P5\OrdenLaboratorio;
use App\Models\P5\ResultadoLaboratorio;
use App\Models\P7\Notificacion;
use App\Models\User;
use Illuminate\Support\Str;

class NotificacionService
{
    /**
     * Registra evento principal de cita médica para el paciente y sus tutores legales (CU16).
     */
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

    /**
     * Notifica al médico tratante que el paciente ha llegado a sala de espera (CU10, CU16).
     */
    public static function notificarLlegadaDoctor(Cita $cita, ?string $operacionId = null): void
    {
        $medicoUser = $cita->medico?->user;
        if (! $medicoUser) {
            return;
        }

        $op = $operacionId ?? (string) Str::uuid();
        $aviso = Notificacion::firstOrCreate([
            'evento_id' => 'llegada:'.$cita->id.':'.$medicoUser->id.':'.$op,
        ], [
            'user_id' => $medicoUser->id,
            'cita_id' => $cita->id,
            'tipo' => 'llegada_paciente',
            'titulo' => 'Paciente en sala de espera',
            'mensaje' => "El paciente {$cita->paciente->nombre_completo} ha registrado su llegada para su turno de las ".substr($cita->hora, 0, 5)." (Ficha {$cita->codigo_ficha}).",
            'leido' => false,
        ]);

        if ($aviso->wasRecentlyCreated) {
            EnviarAviso::dispatch($aviso->id)->afterCommit();
        }
    }

    /**
     * Notifica al personal de laboratorio cuando un médico emite una orden de análisis (CU15, CU16).
     */
    public static function notificarOrdenLaboratorio(OrdenLaboratorio $orden): void
    {
        $orden->loadMissing(['medico.user', 'paciente', 'examenes']);
        $labUsers = User::whereHas('rol', fn ($q) => $q->where('slug', 'lab'))->where('estado', 'activo')->get();
        $totalExamenes = $orden->examenes->count();
        $medicoNombre = $orden->medico?->user?->name ?? 'Médico';
        $pacienteNombre = $orden->paciente?->nombre_completo ?? 'el paciente';

        foreach ($labUsers as $user) {
            $aviso = Notificacion::firstOrCreate([
                'evento_id' => 'lab:nueva_orden:'.$orden->id.':'.$user->id,
            ], [
                'user_id' => $user->id,
                'tipo' => 'laboratorio_orden',
                'titulo' => 'Nueva orden de laboratorio emitida',
                'mensaje' => "El Dr. {$medicoNombre} emitió la orden {$orden->codigo_orden} para {$pacienteNombre} ({$totalExamenes} exámenes).",
                'leido' => false,
            ]);

            if ($aviso->wasRecentlyCreated) {
                EnviarAviso::dispatch($aviso->id)->afterCommit();
            }
        }
    }

    /**
     * Notifica al paciente, tutores y médico tratante cuando un informe de laboratorio es publicado (CU15, CU16).
     */
    public static function notificarResultadoLaboratorio(ResultadoLaboratorio $resultado): void
    {
        $resultado->loadMissing(['orden.paciente.responsables.user', 'orden.medico.user']);
        $paciente = $resultado->orden->paciente;

        $destinatarios = collect();
        if ($paciente && $paciente->user_id) {
            $destinatarios->push($paciente->user_id);
        }
        if ($paciente) {
            foreach ($paciente->responsables as $resp) {
                if ($resp->activo && $resp->user_id) {
                    $destinatarios->push($resp->user_id);
                }
            }
        }

        foreach ($destinatarios->unique() as $userId) {
            $aviso = Notificacion::firstOrCreate([
                'evento_id' => 'lab:pub:'.$resultado->id.':'.$userId,
            ], [
                'user_id' => $userId,
                'tipo' => 'laboratorio',
                'titulo' => 'Resultados de laboratorio disponibles',
                'mensaje' => "El informe de laboratorio {$resultado->codigo_resultado} ha sido publicado y está disponible en su portal clínico para descarga segura.",
                'leido' => false,
            ]);

            if ($aviso->wasRecentlyCreated) {
                EnviarAviso::dispatch($aviso->id)->afterCommit();
            }
        }

        $medicoUser = $resultado->orden->medico?->user;
        if ($medicoUser) {
            $pacienteNombre = $paciente ? $paciente->nombre_completo : 'el paciente';
            $avisoDoc = Notificacion::firstOrCreate([
                'evento_id' => 'lab:pub:doc:'.$resultado->id.':'.$medicoUser->id,
            ], [
                'user_id' => $medicoUser->id,
                'tipo' => 'laboratorio',
                'titulo' => 'Resultados de laboratorio disponibles',
                'mensaje' => "El informe de laboratorio {$resultado->codigo_resultado} del paciente {$pacienteNombre} ha sido publicado y está disponible en su historial.",
                'leido' => false,
            ]);

            if ($avisoDoc->wasRecentlyCreated) {
                EnviarAviso::dispatch($avisoDoc->id)->afterCommit();
            }
        }
    }

    /**
     * Notifica al personal de recepción sobre una inasistencia automática transcurridos 15m (CU8, CU16).
     */
    public static function notificarInasistenciaRecepcion(Cita $cita): void
    {
        $reps = User::whereHas('rol', fn ($q) => $q->where('slug', 'recepcionista'))->where('estado', 'activo')->get();
        foreach ($reps as $rep) {
            $aviso = Notificacion::firstOrCreate([
                'evento_id' => 'inasistencia:rep:'.$cita->id.':'.$rep->id,
            ], [
                'user_id' => $rep->id,
                'cita_id' => $cita->id,
                'tipo' => 'inasistencia_automatica',
                'titulo' => 'Inasistencia automática registrada',
                'mensaje' => "La cita {$cita->codigo_ficha} del paciente {$cita->paciente->nombre_completo} (Turno: ".substr($cita->hora, 0, 5).') se marcó como inasistencia automática.',
                'leido' => false,
            ]);

            if ($aviso->wasRecentlyCreated) {
                EnviarAviso::dispatch($aviso->id)->afterCommit();
            }
        }
    }

    /**
     * Notifica al personal de recepción cuando una oferta exclusiva de lista de espera expira (CU18, CU16).
     */
    public static function notificarOfertaExpiradaRecepcion(OfertaCupo $oferta): void
    {
        $reps = User::whereHas('rol', fn ($q) => $q->where('slug', 'recepcionista'))->where('estado', 'activo')->get();
        $slot = $oferta->slot;
        foreach ($reps as $rep) {
            $aviso = Notificacion::firstOrCreate([
                'evento_id' => 'oferta:expirada:rep:'.$oferta->id.':'.$rep->id,
            ], [
                'user_id' => $rep->id,
                'tipo' => 'oferta_expirada',
                'titulo' => 'Oferta de espera expirada',
                'mensaje' => "La oferta de cupo para el {$slot->fecha->format('d/m/Y')} ".substr($slot->hora_inicio, 0, 5)." con Dr. {$slot->medico->user->name} expiró sin confirmación.",
                'leido' => false,
            ]);

            if ($aviso->wasRecentlyCreated) {
                EnviarAviso::dispatch($aviso->id)->afterCommit();
            }
        }
    }

    /**
     * Notifica al administrador sobre bloqueos de agenda médica de especialistas (CU6, CU16).
     */
    public static function notificarAdminBloqueo(BloqueoAgenda $bloqueo): void
    {
        $admins = User::whereHas('rol', fn ($q) => $q->whereIn('slug', ['admin', 'superadmin']))->where('estado', 'activo')->get();
        foreach ($admins as $admin) {
            $aviso = Notificacion::firstOrCreate([
                'evento_id' => 'bloqueo:admin:'.$bloqueo->id.':'.$admin->id,
            ], [
                'user_id' => $admin->id,
                'tipo' => 'bloqueo_agenda',
                'titulo' => 'Bloqueo de agenda médica registrado',
                'mensaje' => "El Dr. {$bloqueo->medico->user->name} registró un bloqueo de agenda del {$bloqueo->fecha_inicio->format('d/m/Y')} al {$bloqueo->fecha_fin->format('d/m/Y')}. Motivo: {$bloqueo->motivo}.",
                'leido' => false,
            ]);

            if ($aviso->wasRecentlyCreated) {
                EnviarAviso::dispatch($aviso->id)->afterCommit();
            }
        }
    }

    /**
     * Notifica al administrador cuando una cuenta es bloqueada por seguridad tras 3 intentos fallidos (CU1, CU16).
     */
    public static function notificarAdminSeguridad(User $usuario): void
    {
        $admins = User::whereHas('rol', fn ($q) => $q->whereIn('slug', ['admin', 'superadmin']))->where('estado', 'activo')->get();
        $horaClave = now()->format('YmdH');
        foreach ($admins as $admin) {
            $aviso = Notificacion::firstOrCreate([
                'evento_id' => 'seguridad:bloqueo:'.$usuario->id.':'.$admin->id.':'.$horaClave,
            ], [
                'user_id' => $admin->id,
                'tipo' => 'seguridad_bloqueo',
                'titulo' => 'Alerta de seguridad: Cuenta bloqueada',
                'mensaje' => "La cuenta {$usuario->email} (CI: {$usuario->ci}) fue bloqueada temporalmente por 3 intentos fallidos consecutivos de inicio de sesión.",
                'leido' => false,
            ]);

            if ($aviso->wasRecentlyCreated) {
                EnviarAviso::dispatch($aviso->id)->afterCommit();
            }
        }
    }

    /**
     * Notifica al administrador ante fallos críticos de entrega de correo SMTP tras 5 reintentos (CU16).
     */
    public static function notificarAdminFalloSmtp(Notificacion $aviso): void
    {
        $admins = User::whereHas('rol', fn ($q) => $q->whereIn('slug', ['admin', 'superadmin']))->where('estado', 'activo')->get();
        foreach ($admins as $admin) {
            Notificacion::firstOrCreate([
                'evento_id' => 'smtp:fallo:admin:'.$aviso->id.':'.$admin->id,
            ], [
                'user_id' => $admin->id,
                'tipo' => 'smtp_fallido',
                'titulo' => 'Fallo de entrega de correo SMTP',
                'mensaje' => "La notificación #{$aviso->id} ({$aviso->titulo}) para {$aviso->user?->email} no pudo ser entregada tras 5 intentos.",
                'leido' => false,
            ]);
        }
    }
}
