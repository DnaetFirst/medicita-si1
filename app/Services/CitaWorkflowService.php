<?php

namespace App\Services;

use App\Models\P2\Medico;
use App\Models\P2\Slot;
use App\Models\P3\Paciente;
use App\Models\P4\Cita;
use App\Models\P4\HistorialCita;
use App\Models\P4\OfertaCupo;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CitaWorkflowService
{
    public function __construct(public AccesoPacienteService $acceso, public SlotGeneratorService $slots) {}

    public function registrar(Cita $cita, string $operacion, ?Cita $anterior = null, ?string $motivo = null, ?string $operacionId = null, bool $aceptacion = false): HistorialCita
    {
        $evento = HistorialCita::create([
            'cita_id' => $cita->id, 'user_id' => auth()->id(), 'operacion' => $operacion,
            'estado_anterior' => $anterior?->estado, 'estado_nuevo' => $cita->estado,
            'fecha_anterior' => $anterior ? $anterior->fecha->toDateString().' '.$anterior->hora : null,
            'fecha_nueva' => $cita->fecha->toDateString().' '.$cita->hora,
            'slot_anterior_id' => $anterior?->slot_id, 'slot_nuevo_id' => $cita->slot_id,
            'medico_anterior_id' => $anterior?->medico_id, 'medico_nuevo_id' => $cita->medico_id,
            'motivo' => $motivo, 'operacion_id' => $operacionId ?? (string) Str::uuid(),
            'aceptacion_paciente' => $aceptacion,
        ]);
        BitacoraService::registrar($operacion, 'P4_Citas', ['cita_id' => $cita->id, 'historial_id' => $evento->id]);

        return $evento;
    }

    public function validarCupo(Slot $slot, Paciente $paciente, ?int $exceptoCita = null, ?int $exceptoDerivacionCita = null): void
    {
        abort_unless($paciente->activo && $slot->estado === 'disponible', 422, 'El paciente o el turno no están disponibles.');
        abort_unless($slot->medico->estado === 'activo' && $slot->medico->user->estado === 'activo' && $slot->medico->especialidad->activo, 422, 'El médico o la especialidad no están disponibles.');
        abort_if(Carbon::parse($slot->fecha->toDateString().' '.$slot->hora_inicio)->lte(now()), 422, 'El turno debe ser futuro.');
        abort_if($this->slots->estaBloqueado($slot), 422, 'El turno está bloqueado.');
        $ofertaAjena = OfertaCupo::where('slot_id', $slot->id)
            ->where('estado', 'pendiente')
            ->where('fecha_vencimiento', '>', now())
            ->whereHas('listaEspera', fn ($q) => $q->where('paciente_id', '!=', $paciente->id))
            ->exists();
        abort_if($ofertaAjena, 422, 'El turno se encuentra reservado temporalmente para la lista de espera.');
        if ($slot->horario) {
            $horario = $slot->horario;
            abort_unless($horario->medico_id === $slot->medico_id && $horario->dia_semana === $slot->fecha->dayOfWeekIso, 422, 'El turno no corresponde a la franja del profesional.');
            abort_unless($horario->activo && $slot->hora_inicio >= $horario->hora_inicio && $slot->hora_fin <= $horario->hora_fin, 422, 'La franja no está habilitada.');
            abort_if($horario->descanso_inicio && $slot->hora_inicio < $horario->descanso_fin && $slot->hora_fin > $horario->descanso_inicio, 422, 'El turno coincide con un descanso.');
        }

        // Regla: Si el paciente ya tiene una ficha activa, no puede sacar otra ficha
        $citaActiva = Cita::where('paciente_id', $paciente->id)
            ->whereIn('estado', ['programada', 'confirmada', 'en_espera', 'en_atencion'])
            ->when($exceptoCita, fn ($q) => $q->where('id', '!=', $exceptoCita))
            ->when($exceptoDerivacionCita, fn ($q) => $q->where('id', '!=', $exceptoDerivacionCita))
            ->with(['medico.user'])
            ->first();

        if ($citaActiva) {
            $fechaFmt = $citaActiva->fecha ? $citaActiva->fecha->format('d/m/Y') : '';
            $horaFmt = $citaActiva->hora ? substr($citaActiva->hora, 0, 5) : '';
            $medicoNom = $citaActiva->medico?->user?->name ?? 'el especialista asignado';
            abort(422, "El paciente ya cuenta con una ficha médica activa ({$citaActiva->codigo_ficha}) para el {$fechaFmt} a las {$horaFmt} con {$medicoNom}. No es posible sacar otra ficha mientras tenga una activa.");
        }

        $ocupada = Cita::where('paciente_id', $paciente->id)->whereDate('fecha', $slot->fecha)
            ->whereNotIn('estado', ['cancelada', 'postergada', 'inasistencia'])
            ->when($exceptoCita, fn ($q) => $q->where('id', '!=', $exceptoCita))
            ->whereHas('slot', fn ($q) => $q->where('hora_inicio', '<', $slot->hora_fin)->where('hora_fin', '>', $slot->hora_inicio))->exists();
        abort_if($ocupada, 422, 'El paciente ya tiene una cita superpuesta.');
        $medicoOcupado = Cita::where('medico_id', $slot->medico_id)->whereDate('fecha', $slot->fecha)
            ->whereNotIn('estado', ['cancelada', 'postergada'])
            ->when($exceptoCita, fn ($q) => $q->where('id', '!=', $exceptoCita))
            ->whereHas('slot', fn ($q) => $q->where('hora_inicio', '<', $slot->hora_fin)->where('hora_fin', '>', $slot->hora_inicio))->exists();
        abort_if($medicoOcupado, 422, 'El profesional ya tiene un turno ocupado en esa franja.');
    }

    /** @param array<string, mixed> $datos */
    public function ejecutar(Cita $referencia, User $user, string $accion, array $datos = []): Cita
    {
        return DB::transaction(function () use ($referencia, $user, $accion, $datos): Cita {
            $nuevo = isset($datos['nuevo_slot_id']) ? Slot::findOrFail($datos['nuevo_slot_id']) : null;
            Medico::whereIn('id', array_filter([$referencia->medico_id, $nuevo?->medico_id]))->orderBy('id')->lockForUpdate()->get();
            Paciente::whereKey($referencia->paciente_id)->lockForUpdate()->firstOrFail();
            $cita = Cita::whereKey($referencia->id)->lockForUpdate()->firstOrFail();
            abort_unless($cita->medico_id === $referencia->medico_id, 409, 'La cita cambió. Actualiza la página.');
            $this->acceso->autorizarCita($user, $cita, $accion);
            $operacionId = $datos['operacion_id'] ?? null;
            if ($operacionId && ($previo = HistorialCita::where('operacion_id', $operacionId)->first())) {
                abort_unless($previo->cita_id === $cita->id && $previo->user_id === $user->id && $previo->operacion === strtoupper($accion), 409);

                return $cita;
            }
            if (isset($datos['version'])) {
                abort_unless((int) $datos['version'] === $cita->version, 409, 'La cita cambió. Actualiza la página.');
            }
            $anterior = clone $cita;
            $inicio = Carbon::parse($cita->fecha->toDateString().' '.$cita->hora);
            if ($accion === 'reprogramar') {
                abort_unless(in_array($cita->estado, ['programada', 'confirmada', 'postergada']), 422);
                abort_if($user->isPaciente() && $cita->estado !== 'postergada' && $inicio->lte(now()), 422);
                abort_unless($datos['aceptacion_paciente'] ?? false, 422, 'Registra la aceptación expresa del paciente.');
                $nuevo = Slot::whereKey($nuevo->id)->lockForUpdate()->firstOrFail();
                abort_unless($nuevo->id !== $cita->slot_id && $nuevo->medico->especialidad_id === $cita->medico->especialidad_id, 422, 'Selecciona otro turno de la misma especialidad.');
                $this->validarCupo($nuevo, $cita->paciente, $cita->id);
                $nuevo->update(['estado' => 'reservado']);
                $cita->fill(['slot_id' => $nuevo->id, 'medico_id' => $nuevo->medico_id, 'fecha' => $nuevo->fecha, 'hora' => $nuevo->hora_inicio,
                    'estado' => 'programada', 'hora_confirmacion' => null, 'hora_llegada' => null, 'hora_checkin' => null]);
            } elseif ($accion === 'cancelar') {
                abort_unless(in_array($cita->estado, ['programada', 'confirmada', 'postergada']), 422);
                abort_if($user->isPaciente() && $cita->estado !== 'postergada' && $inicio->lte(now()), 422, 'Contacta con recepción para esta cita.');
                $cita->fill(['estado' => 'cancelada', 'motivo_cancelacion' => $datos['motivo'], 'cancelado_por_user_id' => $user->id]);
            } elseif ($accion === 'confirmar') {
                abort_unless($cita->estado === 'programada' && $inicio->gt(now()), 422);
                $cita->fill(['estado' => 'confirmada', 'hora_confirmacion' => now()]);
            } elseif ($accion === 'llegada') {
                abort_unless(in_array($cita->estado, ['programada', 'confirmada']) && $cita->fecha->isToday(), 422, 'La llegada se registra el día de la cita.');
                abort_if($this->slots->estaBloqueado($cita->slot), 422);
                $cita->fill(['estado' => 'en_espera', 'hora_llegada' => now(), 'hora_checkin' => now()]);
            } elseif ($accion === 'iniciar') {
                abort_unless($cita->estado === 'en_espera' && $cita->fecha->isToday(), 422);
                abort_if($this->slots->estaBloqueado($cita->slot), 422);
                $cita->fill(['estado' => 'en_atencion', 'hora_inicio_atencion' => now()]);
            } elseif ($accion === 'finalizar') {
                abort_unless($cita->estado === 'en_atencion', 422);
                $cita->fill(['estado' => 'atendida', 'hora_fin_atencion' => now()]);
            } elseif ($accion === 'inasistencia') {
                abort_unless(in_array($cita->estado, ['programada', 'confirmada']) && $inicio->copy()->addMinutes(15)->lte(now()), 422, 'Espera hasta el final del cupo.');
                abort_if($this->slots->estaBloqueado($cita->slot), 422, 'Una suspensión no es inasistencia.');
                $cita->estado = 'inasistencia';
            }
            $cita->version++;
            $cita->save();
            $evento = $this->registrar($cita, strtoupper($accion), $anterior, $datos['motivo'] ?? null, $operacionId, (bool) ($datos['aceptacion_paciente'] ?? false));
            if (in_array($accion, ['cancelar', 'reprogramar'])) {
                $this->slots->liberarSlot($anterior->slot);
                NotificacionService::registrarEvento($cita, $evento->operacion_id, $accion);
                app(RecuperacionCupoService::class)->evaluarRecuperacion($anterior->slot);
            }

            return $cita;
        }, 3);
    }
}
