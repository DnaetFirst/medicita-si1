<?php

namespace App\Services;

use App\Models\P2\BloqueoAgenda;
use App\Models\P2\HorarioAtencion;
use App\Models\P2\Medico;
use App\Models\P2\Slot;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SlotGeneratorService
{
    /**
     * Genera los slots de 15 minutos para un médico en una fecha específica.
     * Respeta el descanso obligatorio de 1 hora y los bloqueos existentes.
     */
    public function generarSlotsParaFecha(Medico $medico, string $fecha): Collection
    {
        $fechaCarbon = Carbon::parse($fecha);
        $diaSemana = $fechaCarbon->isoDayOfWeek; // 1 = Lunes, 7 = Domingo

        $horario = HorarioAtencion::where('medico_id', $medico->id)
            ->where('dia_semana', $diaSemana)
            ->where('activo', true)
            ->first();

        if (!$horario) {
            return collect(); // El médico no atiende este día
        }

        $duracion = $horario->duracion_slot_minutos ?? 15;
        $inicio = Carbon::parse("{$fecha} {$horario->hora_inicio}");
        $fin = Carbon::parse("{$fecha} {$horario->hora_fin}");
        $descansoInicio = Carbon::parse("{$fecha} {$horario->descanso_inicio}");
        $descansoFin = Carbon::parse("{$fecha} {$horario->descanso_fin}"); // Exactamente 1 hora

        // Verificar si hay un bloqueo de agenda para este día
        $bloqueos = BloqueoAgenda::where('medico_id', $medico->id)
            ->where(function ($query) use ($fecha) {
                $query->whereDate('fecha_inicio', '<=', $fecha)
                      ->whereDate('fecha_fin', '>=', $fecha);
            })->get();

        $slotsCreados = collect();
        $cursor = $inicio->copy();

        while ($cursor->copy()->addMinutes($duracion)->lte($fin)) {
            $slotInicio = $cursor->format('H:i');
            $cursor->addMinutes($duracion);
            $slotFin = $cursor->format('H:i');

            // Determinar estado inicial del slot
            $estado = 'disponible';

            // 1. Regla de descanso obligatorio de 1 hora
            $horaCarbonSlot = Carbon::parse("{$fecha} {$slotInicio}");
            if ($horaCarbonSlot->gte($descansoInicio) && $horaCarbonSlot->lt($descansoFin)) {
                $estado = 'descanso';
            }

            // 2. Regla de bloqueo de agenda
            if ($estado !== 'descanso') {
                foreach ($bloqueos as $bloqueo) {
                    $bloqueoIni = Carbon::parse($bloqueo->fecha_inicio);
                    $bloqueoFin = Carbon::parse($bloqueo->fecha_fin);
                    if ($horaCarbonSlot->gte($bloqueoIni) && $horaCarbonSlot->lt($bloqueoFin)) {
                        $estado = 'bloqueado';
                        break;
                    }
                }
            }

            // Registrar o recuperar slot persistido
            $slot = Slot::firstOrCreate(
                [
                    'medico_id' => $medico->id,
                    'fecha' => $fecha,
                    'hora_inicio' => $slotInicio,
                ],
                [
                    'horario_id' => $horario->id,
                    'hora_fin' => $slotFin,
                    'estado' => $estado,
                ]
            );

            $slotsCreados->push($slot);
        }

        return $slotsCreados;
    }
}
