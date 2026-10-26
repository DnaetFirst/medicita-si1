<?php

namespace App\Services;

use App\Models\P2\BloqueoAgenda;
use App\Models\P2\HorarioAtencion;
use App\Models\P2\Medico;
use App\Models\P2\Slot;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class SlotGeneratorService
{
    public function estaBloqueado(Slot $slot): bool
    {
        $inicio = Carbon::parse($slot->fecha->toDateString().' '.$slot->hora_inicio);
        $fin = Carbon::parse($slot->fecha->toDateString().' '.$slot->hora_fin);

        return BloqueoAgenda::where('medico_id', $slot->medico_id)
            ->whereDate('fecha_inicio', '<=', $slot->fecha)
            ->whereDate('fecha_fin', '>=', $slot->fecha)
            ->get()->contains(function (BloqueoAgenda $bloqueo) use ($inicio, $fin): bool {
                $bloqueoFin = $bloqueo->fecha_fin->copy();
                if ($bloqueo->fecha_inicio->equalTo($bloqueoFin)) {
                    $bloqueoFin->endOfDay();
                }

                return $inicio->lt($bloqueoFin) && $fin->gt($bloqueo->fecha_inicio);
            });
    }

    public function liberarSlot(Slot $slot, bool $preservarReserva = false): void
    {
        $estado = 'disponible';
        if ($slot->horario_id) {
            $horario = $slot->horario;
            $inicio = substr($slot->hora_inicio, 0, 5);
            $fin = substr($slot->hora_fin, 0, 5);
            if (! $horario->activo || $inicio < substr($horario->hora_inicio, 0, 5) || $fin > substr($horario->hora_fin, 0, 5)) {
                $estado = 'bloqueado';
            } elseif ($inicio < substr($horario->descanso_fin, 0, 5) && $fin > substr($horario->descanso_inicio, 0, 5)) {
                $estado = 'descanso';
            }
        }
        if ($estado === 'disponible' && $this->estaBloqueado($slot)) {
            $estado = 'bloqueado';
        }
        $query = Slot::whereKey($slot->id);
        if ($preservarReserva) {
            $query->where('estado', '!=', 'reservado');
        }
        $query->update(['estado' => $estado]);
    }

    public function generarSlotsParaFecha(Medico $medico, string $fecha): Collection
    {
        $fechaCarbon = Carbon::parse($fecha);
        $horario = HorarioAtencion::where('medico_id', $medico->id)
            ->where('dia_semana', $fechaCarbon->dayOfWeekIso)->where('activo', true)->first();

        Slot::where('medico_id', $medico->id)->whereDate('fecha', $fecha)
            ->where('estado', '!=', 'reservado')->update(['estado' => 'bloqueado']);

        if ($horario) {
            $cursor = Carbon::parse($fecha.' '.$horario->hora_inicio);
            $fin = Carbon::parse($fecha.' '.$horario->hora_fin);
            while ($cursor->copy()->addMinutes(15)->lte($fin)) {
                $inicio = $cursor->format('H:i');
                $cursor->addMinutes(15);
                $slot = Slot::firstOrCreate([
                    'medico_id' => $medico->id,
                    'fecha' => $fechaCarbon->copy()->startOfDay(),
                    'hora_inicio' => $inicio,
                ], [
                    'horario_id' => $horario->id,
                    'hora_fin' => $cursor->format('H:i'),
                    'estado' => 'disponible',
                ]);
                if ($slot->estado !== 'reservado') {
                    Slot::whereKey($slot->id)->where('estado', '!=', 'reservado')
                        ->update(['horario_id' => $horario->id, 'hora_fin' => $cursor->format('H:i')]);
                    $slot->refresh();
                    $this->liberarSlot($slot, preservarReserva: true);
                }
            }
        }

        return Slot::with('cita.paciente')->where('medico_id', $medico->id)
            ->whereDate('fecha', $fecha)->orderBy('hora_inicio')->get();
    }
}
