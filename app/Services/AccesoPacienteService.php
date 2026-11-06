<?php

namespace App\Services;

use App\Models\P3\Paciente;
use App\Models\P4\Cita;
use App\Models\P5\ResultadoLaboratorio;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AccesoPacienteService
{
    public function pacientes(User $user): Builder
    {
        $query = Paciente::query();
        if ($user->isAdmin() || $user->isRecepcionista()) {
            return $query;
        }
        if ($user->isMedico()) {
            return $query->whereHas('citas', fn (Builder $citas) => $citas->where('medico_id', $user->medico?->id ?? 0));
        }
        if ($user->isPaciente()) {
            return $query->where(function (Builder $pacientes) use ($user): void {
                $pacientes->where('user_id', $user->id)->orWhereHas('responsables', function (Builder $responsables) use ($user): void {
                    $responsables->where('user_id', $user->id)->where('activo', true)
                        ->where('puede_gestionar_citas', true)->whereDate('fecha_limite_vigencia', '>=', today())
                        ->where(fn (Builder $vigencia) => $vigencia->whereNull('vigencia_inicio')->orWhereDate('vigencia_inicio', '<=', today()));
                });
            });
        }

        return $query->whereRaw('1 = 0');
    }

    public function pacientesResultados(User $user): Builder
    {
        $query = Paciente::query();
        if ($user->isAdmin()) {
            return $query;
        }
        if ($user->isMedico()) {
            return $query->whereHas('citas', fn (Builder $citas) => $citas->where('medico_id', $user->medico?->id ?? 0));
        }
        if ($user->isPaciente()) {
            return $query->where(function (Builder $pacientes) use ($user): void {
                $pacientes->where('user_id', $user->id)->orWhereHas('responsables', function (Builder $responsables) use ($user): void {
                    $responsables->where('user_id', $user->id)->where('activo', true)
                        ->where('puede_ver_resultados', true)->whereDate('fecha_limite_vigencia', '>=', today())
                        ->where(fn (Builder $vigencia) => $vigencia->whereNull('vigencia_inicio')->orWhereDate('vigencia_inicio', '<=', today()));
                });
            });
        }
        if ($user->rol?->slug === 'lab') {
            return $query;
        }

        return $query->whereRaw('1 = 0');
    }

    public function autorizar(User $user, Paciente $paciente): void
    {
        abort_unless($user->estado === 'activo' && $this->pacientes($user)->whereKey($paciente->id)->exists(), 403);
    }

    public function autorizarClinica(User $user, Paciente $paciente): void
    {
        abort_unless($user->isMedico() && $user->medico?->estado === 'activo', 403);
        $this->autorizar($user, $paciente);
    }

    public function autorizarCita(User $user, Cita $cita, string $accion = 'ver'): void
    {
        $this->autorizar($user, $cita->paciente);
        if ($user->isMedico()) {
            abort_unless($cita->medico_id === $user->medico?->id, 403);
        }
        $permitido = match ($accion) {
            'confirmar' => $user->isPaciente(),
            'llegada', 'inasistencia' => $user->isRecepcionista(),
            'iniciar', 'finalizar' => $user->isMedico() && $user->medico?->estado === 'activo',
            'cancelar', 'reprogramar' => $user->isPaciente() || $user->isRecepcionista() || $user->isAdmin(),
            default => true,
        };
        abort_unless($permitido, 403);
    }

    public function autorizarResultado(User $user, ResultadoLaboratorio $resultado): void
    {
        abort_unless($user->estado === 'activo', 403);
        if ($user->rol?->slug === 'lab') {
            return;
        }

        // Médicos y pacientes solo pueden consultar resultados que estén efectivamente publicados
        abort_unless($resultado->isPublicado(), 403, 'El resultado aún no ha sido publicado.');

        $paciente = $resultado->orden->paciente;
        abort_unless($this->pacientesResultados($user)->whereKey($paciente->id)->exists(), 403);

        if ($user->isMedico()) {
            // Médico debe ser el solicitante o tener relación asistencial activa con el paciente
            $esSolicitante = $resultado->orden->medico_id === $user->medico?->id;
            $tieneCita = $paciente->citas()->where('medico_id', $user->medico?->id ?? 0)->exists();
            abort_unless($esSolicitante || $tieneCita, 403);
        }
    }
}
