<?php

namespace App\Jobs;

use App\Mail\AvisoCitaMail;
use App\Models\P4\Cita;
use App\Models\P7\Notificacion;
use App\Services\AccesoPacienteService;
use App\Services\NotificacionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EnviarAviso implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $avisoId) {}

    public function handle(): void
    {
        DB::transaction(function (): void {
            $aviso = Notificacion::whereKey($this->avisoId)->lockForUpdate()->firstOrFail();
            if ($aviso->enviado_at || $aviso->estado_envio === 'revocado' || $aviso->intentos >= 5) {
                return;
            }
            if (! $aviso->user?->email || $aviso->user->estado !== 'activo') {
                return;
            }
            $cita = Cita::find($aviso->cita_id);
            if ($cita && ! app(AccesoPacienteService::class)->pacientes($aviso->user)->whereKey($cita->paciente_id)->exists()) {
                $aviso->update(['estado_envio' => 'revocado']);

                return;
            }
            $aviso->increment('intentos');
            try {
                Mail::to($aviso->user->email)->send(new AvisoCitaMail($aviso));
                $aviso->update(['estado_envio' => 'enviado', 'enviado_at' => now()]);
            } catch (Throwable $exception) {
                $aviso->update(['estado_envio' => 'fallido']);
                Log::warning('Fallo de envío de aviso', ['aviso_id' => $aviso->id]);
                if ($aviso->intentos >= 5) {
                    NotificacionService::notificarAdminFalloSmtp($aviso);
                }
            }
        });
    }
}
