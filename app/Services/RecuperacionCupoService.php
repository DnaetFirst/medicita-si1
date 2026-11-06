<?php

namespace App\Services;

use App\Models\P2\Medico;
use App\Models\P2\Slot;
use App\Models\P3\Paciente;
use App\Models\P4\Cita;
use App\Models\P4\ListaEspera;
use App\Models\P4\OfertaCupo;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecuperacionCupoService
{
    public function __construct(
        public AccesoPacienteService $acceso,
        public SlotGeneratorService $slotGenerator,
        public CitaWorkflowService $workflow
    ) {}

    /**
     * Inscribe a un paciente en la lista de espera (CU18).
     *
     * @param  array<string, mixed>  $datos
     */
    public function inscribir(Paciente $paciente, array $datos, User $user): ListaEspera
    {
        $this->acceso->autorizar($user, $paciente);

        return DB::transaction(function () use ($paciente, $datos): ListaEspera {
            $citaPrevia = null;
            if (! empty($datos['cita_id'])) {
                $citaPrevia = Cita::whereKey($datos['cita_id'])
                    ->where('paciente_id', $paciente->id)
                    ->whereIn('estado', ['programada', 'confirmada'])
                    ->firstOrFail();
            }

            $lista = ListaEspera::create([
                'paciente_id' => $paciente->id,
                'especialidad_id' => $datos['especialidad_id'],
                'medico_id' => $datos['medico_id'] ?? null,
                'fecha_desde' => $datos['fecha_desde'],
                'fecha_hasta' => $datos['fecha_hasta'],
                'franja_horaria' => $datos['franja_horaria'] ?? 'cualquiera',
                'anticipacion_minima_horas' => $datos['anticipacion_minima_horas'] ?? 2,
                'cita_id' => $citaPrevia?->id,
                'estado' => 'activa',
                'fecha_inscripcion' => today(),
                'operacion_id' => $datos['operacion_id'] ?? (string) Str::uuid(),
            ]);

            BitacoraService::registrar('INSCRIBIR_LISTA_ESPERA', 'P4_Citas', [
                'lista_espera_id' => $lista->id,
                'paciente_id' => $paciente->id,
                'especialidad_id' => $lista->especialidad_id,
            ]);

            return $lista;
        });
    }

    /**
     * Evalúa si hay candidatos compatibles en lista de espera ante la liberación de un slot.
     * Crea una oferta temporal exclusiva si encuentra un candidato (CU18).
     */
    public function evaluarRecuperacion(Slot $slot): ?OfertaCupo
    {
        if ($this->slotGenerator->estaBloqueado($slot)) {
            return null;
        }

        $inicioSlot = Carbon::parse($slot->fecha->toDateString().' '.$slot->hora_inicio);
        if ($inicioSlot->lte(now())) {
            return null;
        }

        // Si ya hay una oferta pendiente no vencida para este slot, no crear otra
        $ofertaActiva = OfertaCupo::where('slot_id', $slot->id)
            ->where('estado', 'pendiente')
            ->where('fecha_vencimiento', '>', now())
            ->first();
        if ($ofertaActiva) {
            return $ofertaActiva;
        }

        return DB::transaction(function () use ($slot, $inicioSlot): ?OfertaCupo {
            $slot = Slot::whereKey($slot->id)->lockForUpdate()->firstOrFail();
            if ($slot->estado !== 'disponible') {
                return null;
            }

            // Excluir pacientes que ya rechazaron o dejaron vencer una oferta para este slot específico
            $pacientesYaOfertados = OfertaCupo::where('slot_id', $slot->id)
                ->whereIn('estado', ['rechazada', 'vencida'])
                ->with('listaEspera')
                ->get()
                ->pluck('listaEspera.paciente_id')
                ->filter()
                ->unique();

            $query = ListaEspera::where('estado', 'activa')
                ->where('especialidad_id', $slot->medico->especialidad_id)
                ->whereDate('fecha_desde', '<=', $slot->fecha)
                ->whereDate('fecha_hasta', '>=', $slot->fecha)
                ->whereNotIn('paciente_id', $pacientesYaOfertados)
                ->where(function ($q) use ($slot): void {
                    $q->whereNull('medico_id')->orWhere('medico_id', $slot->medico_id);
                });

            // Filtro de franja horaria
            $esManana = $slot->hora_inicio < '13:00';
            $query->where(function ($q) use ($esManana): void {
                $q->where('franja_horaria', 'cualquiera')
                    ->orWhere('franja_horaria', $esManana ? 'manana' : 'tarde');
            });

            // FIFO por orden de inscripción y desempate por id
            $candidatos = $query->orderBy('fecha_inscripcion')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($candidatos as $candidato) {
                // Verificar anticipación mínima en horas
                $horasAnticipacion = now()->diffInHours($inicioSlot, false);
                if ($horasAnticipacion < $candidato->anticipacion_minima_horas) {
                    continue;
                }

                // Si pidió adelantar cita, comprobar que el nuevo cupo sea anterior a su cita previa
                if ($candidato->citaPrevia) {
                    $inicioCitaPrevia = Carbon::parse($candidato->citaPrevia->fecha->toDateString().' '.$candidato->citaPrevia->hora);
                    if ($inicioSlot->gte($inicioCitaPrevia)) {
                        continue;
                    }
                }

                // Verificar que el paciente no tenga superposición horaria
                $superpuesto = Cita::where('paciente_id', $candidato->paciente_id)
                    ->whereDate('fecha', $slot->fecha)
                    ->whereNotIn('estado', ['cancelada', 'postergada', 'inasistencia'])
                    ->whereHas('slot', fn ($q) => $q->where('hora_inicio', '<', $slot->hora_fin)->where('hora_fin', '>', $slot->hora_inicio))
                    ->exists();

                if ($superpuesto) {
                    continue;
                }

                // Crear oferta exclusiva con retención de 10 minutos (o hasta el inicio de la consulta)
                $vencimiento = now()->addMinutes(10);
                if ($vencimiento->gt($inicioSlot)) {
                    $vencimiento = $inicioSlot;
                }

                if ($vencimiento->lte(now())) {
                    continue;
                }

                $oferta = OfertaCupo::create([
                    'lista_espera_id' => $candidato->id,
                    'slot_id' => $slot->id,
                    'estado' => 'pendiente',
                    'fecha_oferta' => now(),
                    'fecha_vencimiento' => $vencimiento,
                    'operacion_id' => (string) Str::uuid(),
                ]);

                // Notificar al paciente de la oferta de cupo recuperado
                $this->notificarOferta($oferta);

                BitacoraService::registrar('CREAR_OFERTA_CUPO', 'P4_Citas', [
                    'oferta_id' => $oferta->id,
                    'lista_espera_id' => $candidato->id,
                    'slot_id' => $slot->id,
                    'vencimiento' => $vencimiento->toDateTimeString(),
                ]);

                return $oferta;
            }

            return null;
        }, 3);
    }

    /**
     * Acepta una oferta de cupo recuperado de forma atómica (CU18).
     */
    public function aceptarOferta(OfertaCupo $oferta, User $user, ?string $operacionId = null): Cita
    {
        return DB::transaction(function () use ($oferta, $user, $operacionId): Cita {
            $oferta = OfertaCupo::whereKey($oferta->id)->lockForUpdate()->firstOrFail();
            $lista = ListaEspera::whereKey($oferta->lista_espera_id)->lockForUpdate()->firstOrFail();
            $slot = Slot::whereKey($oferta->slot_id)->lockForUpdate()->firstOrFail();

            $this->acceso->autorizar($user, $lista->paciente);

            // Revalidar vigencia estricta en el servidor
            abort_unless($oferta->isPendiente(), 422, 'La oferta ya no se encuentra pendiente.');
            abort_if(now()->gte($oferta->fecha_vencimiento), 422, 'La oferta ha vencido.');

            if ($this->slotGenerator->estaBloqueado($slot)) {
                $oferta->update(['estado' => 'anulada', 'respondida_at' => now(), 'motivo_respuesta' => 'Turno bloqueado por la clínica']);
                abort(422, 'El turno ha sido bloqueado por razones médicas.');
            }

            // Si el paciente tenía cita previa a adelantar: reprogramar la misma cita
            if ($lista->cita_id && ($citaPrevia = Cita::find($lista->cita_id))) {
                $citaActualizada = $this->workflow->ejecutar($citaPrevia, $user, 'reprogramar', [
                    'nuevo_slot_id' => $slot->id,
                    'motivo' => 'Adelanto de cita por lista de espera',
                    'aceptacion_paciente' => true,
                    'operacion_id' => $operacionId ?? (string) Str::uuid(),
                ]);

                $citaActualizada->update([
                    'es_cupo_recuperado' => true,
                    'oferta_cupo_id' => $oferta->id,
                ]);

                $citaFinal = $citaActualizada;
            } else {
                // Reserva de cita nueva por recuperación de cupo
                $slot->update(['estado' => 'reservado']);
                $codigoFicha = 'FCH-'.$slot->fecha->format('Ymd').'-'.strtoupper(Str::random(16));

                $citaNueva = Cita::create([
                    'codigo_ficha' => $codigoFicha,
                    'paciente_id' => $lista->paciente_id,
                    'medico_id' => $slot->medico_id,
                    'slot_id' => $slot->id,
                    'fecha' => $slot->fecha,
                    'hora' => $slot->hora_inicio,
                    'estado' => 'programada',
                    'motivo_consulta' => 'Cita agendada por recuperación de cupo',
                    'es_cupo_recuperado' => true,
                    'oferta_cupo_id' => $oferta->id,
                    'operacion_id' => $operacionId ?? (string) Str::uuid(),
                ]);

                $this->workflow->registrar($citaNueva, 'RESERVA_RECUPERADA', null, 'Asignación por lista de espera');
                NotificacionService::registrarEvento($citaNueva, $oferta->operacion_id, 'reserva');

                $citaFinal = $citaNueva;
            }

            $oferta->update([
                'estado' => 'aceptada',
                'respondida_at' => now(),
            ]);

            $lista->update(['estado' => 'atendida']);

            BitacoraService::registrar('ACEPTAR_OFERTA_CUPO', 'P4_Citas', [
                'oferta_id' => $oferta->id,
                'cita_id' => $citaFinal->id,
                'paciente_id' => $lista->paciente_id,
            ]);

            return $citaFinal;
        }, 3);
    }

    /**
     * Rechaza una oferta y avanza al siguiente candidato de la lista de espera (CU18).
     */
    public function rechazarOferta(OfertaCupo $oferta, User $user, ?string $motivo = null): void
    {
        DB::transaction(function () use ($oferta, $user, $motivo): void {
            $oferta = OfertaCupo::whereKey($oferta->id)->lockForUpdate()->firstOrFail();
            $lista = ListaEspera::whereKey($oferta->lista_espera_id)->lockForUpdate()->firstOrFail();
            $slot = Slot::whereKey($oferta->slot_id)->lockForUpdate()->firstOrFail();

            $this->acceso->autorizar($user, $lista->paciente);
            abort_unless($oferta->isPendiente(), 422, 'La oferta no se encuentra pendiente.');

            $oferta->update([
                'estado' => 'rechazada',
                'respondida_at' => now(),
                'motivo_respuesta' => $motivo ?? 'Rechazada por el paciente',
            ]);

            BitacoraService::registrar('RECHAZAR_OFERTA_CUPO', 'P4_Citas', [
                'oferta_id' => $oferta->id,
                'paciente_id' => $lista->paciente_id,
            ]);

            // Re-evaluar el slot para el siguiente candidato de la lista
            $siguienteOferta = $this->evaluarRecuperacion($slot);
            if (! $siguienteOferta && $slot->estado !== 'reservado') {
                $this->slotGenerator->liberarSlot($slot);
            }
        }, 3);
    }

    /**
     * Procesa ofertas vencidas y busca al siguiente candidato en lista de espera.
     * Ejecutable de forma periódica por el scheduler de Laravel.
     */
    public function procesarOfertasVencidas(): int
    {
        $vencidas = OfertaCupo::where('estado', 'pendiente')
            ->where('fecha_vencimiento', '<=', now())
            ->lockForUpdate()
            ->get();

        $procesadas = 0;
        foreach ($vencidas as $oferta) {
            DB::transaction(function () use ($oferta, &$procesadas): void {
                $oferta->update(['estado' => 'vencida', 'respondida_at' => now()]);
                $procesadas++;

                BitacoraService::registrar('EXPIRAR_OFERTA_CUPO', 'P4_Citas', [
                    'oferta_id' => $oferta->id,
                    'slot_id' => $oferta->slot_id,
                ]);

                // Avanzar al siguiente candidato
                $siguiente = $this->evaluarRecuperacion($oferta->slot);
                if (! $siguiente && $oferta->slot->estado !== 'reservado') {
                    $this->slotGenerator->liberarSlot($oferta->slot);
                }
            });
        }

        return $procesadas;
    }

    /**
     * Notifica al paciente y sus tutores autorizados sobre la disponibilidad de un cupo.
     */
    protected function notificarOferta(OfertaCupo $oferta): void
    {
        $paciente = $oferta->listaEspera->paciente;
        $slot = $oferta->slot;
        $medico = $slot->medico;

        $destinatarios = collect([$paciente->user_id]);
        $destinatarios = $destinatarios->merge($paciente->responsables()
            ->where('activo', true)
            ->where('puede_gestionar_citas', true)
            ->whereDate('fecha_limite_vigencia', '>=', today())
            ->where(fn ($q) => $q->whereNull('vigencia_inicio')->orWhereDate('vigencia_inicio', '<=', today()))
            ->pluck('user_id'));

        foreach ($destinatarios->filter()->unique() as $userId) {
            $user = User::find($userId);
            if (! $user) {
                continue;
            }

            $aviso = \App\Models\P7\Notificacion::firstOrCreate([
                'evento_id' => 'oferta:'.$oferta->id.':'.$userId,
            ], [
                'user_id' => $userId,
                'tipo' => 'oferta_cupo',
                'titulo' => '¡Cupo disponible por lista de espera!',
                'mensaje' => "Se ha liberado un turno para {$medico->especialidad->nombre} con el Dr. {$medico->user->name} el {$slot->fecha->format('d/m/Y')} a las {$slot->hora_inicio}. Tienes 10 minutos para confirmar tu cupo.",
                'leido' => false,
            ]);

            if ($aviso->wasRecentlyCreated) {
                \App\Jobs\EnviarAviso::dispatch($aviso->id)->afterCommit();
            }
        }
    }
}
