<?php

namespace App\Services;

use App\Models\P2\Especialidad;
use App\Models\P2\Medico;
use App\Models\P2\Slot;
use App\Models\P4\Cita;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ReporteIndicadoresService
{
    /**
     * Calcula los KPIs clínicos y operacionales según los filtros aplicados.
     *
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function calcularKpis(array $filtros = []): array
    {
        $query = $this->aplicarFiltros(Cita::query(), $filtros);

        $totalCitas = (clone $query)->count();
        $completadas = (clone $query)->whereIn('estado', ['atendida', 'completada', 'en_atencion'])->count();
        $canceladas = (clone $query)->where('estado', 'cancelada')->count();
        $inasistencias = (clone $query)->where('estado', 'inasistencia')->count();
        $reprogramadas = (clone $query)->where('estado', 'reprogramada')->count();
        $postergadas = (clone $query)->where('estado', 'postergada')->count();
        $cuposRecuperados = (clone $query)->where('es_cupo_recuperado', true)->count();

        $tasaAsistencia = $totalCitas > 0 ? round(($completadas / $totalCitas) * 100, 1) : 0.0;
        $tasaCancelacion = $totalCitas > 0 ? round(($canceladas / $totalCitas) * 100, 1) : 0.0;
        $tasaNoShow = $totalCitas > 0 ? round(($inasistencias / $totalCitas) * 100, 1) : 0.0;
        $tasaRecuperacion = $canceladas > 0 ? round(($cuposRecuperados / $canceladas) * 100, 1) : ($cuposRecuperados > 0 ? 100.0 : 0.0);

        // Ocupación de turnos (Slots)
        $slotQuery = Slot::query();
        if (! empty($filtros['fecha_inicio'])) {
            $slotQuery->whereDate('fecha', '>=', $filtros['fecha_inicio']);
        }
        if (! empty($filtros['fecha_fin'])) {
            $slotQuery->whereDate('fecha', '<=', $filtros['fecha_fin']);
        }
        if (! empty($filtros['medico_id'])) {
            $slotQuery->where('medico_id', $filtros['medico_id']);
        }
        if (! empty($filtros['especialidad_id'])) {
            $slotQuery->whereHas('medico', function (Builder $q) use ($filtros) {
                $q->where('especialidad_id', $filtros['especialidad_id']);
            });
        }

        $totalSlots = (clone $slotQuery)->count();
        $slotsReservados = (clone $slotQuery)->where('estado', 'reservado')->count();
        $slotsDisponibles = (clone $slotQuery)->where('estado', 'disponible')->count();
        $slotsBloqueados = (clone $slotQuery)->where('estado', 'bloqueado')->count();
        $tasaOcupacion = $totalSlots > 0 ? round(($slotsReservados / $totalSlots) * 100, 1) : 0.0;

        return [
            'total_citas' => $totalCitas,
            'completadas' => $completadas,
            'canceladas' => $canceladas,
            'inasistencias' => $inasistencias,
            'reprogramadas' => $reprogramadas,
            'postergadas' => $postergadas,
            'cupos_recuperados' => $cuposRecuperados,
            'tasa_asistencia' => $tasaAsistencia,
            'tasa_cancelacion' => $tasaCancelacion,
            'tasa_no_show' => $tasaNoShow,
            'tasa_recuperacion' => $tasaRecuperacion,
            'total_slots' => $totalSlots,
            'slots_reservados' => $slotsReservados,
            'slots_disponibles' => $slotsDisponibles,
            'slots_bloqueados' => $slotsBloqueados,
            'tasa_ocupacion' => $tasaOcupacion,
        ];
    }

    /**
     * Obtiene las citas paginadas según filtros.
     *
     * @param  array<string, mixed>  $filtros
     */
    public function obtenerListadoCitas(array $filtros = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->aplicarFiltros(Cita::query(), $filtros)
            ->with(['paciente.user', 'medico.user', 'medico.especialidad', 'slot'])
            ->orderBy('fecha', 'desc')
            ->orderBy('hora', 'desc')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Genera un CSV estructurado con cabeceras y BOM UTF-8 para Excel.
     *
     * @param  array<string, mixed>  $filtros
     */
    public function generarCsv(array $filtros = []): string
    {
        $citas = $this->aplicarFiltros(Cita::query(), $filtros)
            ->with(['paciente.user', 'medico.user', 'medico.especialidad', 'slot'])
            ->orderBy('fecha', 'desc')
            ->orderBy('hora', 'desc')
            ->get();

        $output = fopen('php://temp', 'r+');
        // UTF-8 BOM
        fputs($output, "\xEF\xBB\xBF");

        fputcsv($output, [
            'ID',
            'Codigo Ficha',
            'Fecha',
            'Hora',
            'Estado',
            'Especialidad',
            'Medico',
            'Paciente',
            'Documento CI',
            'Cupo Recuperado',
            'Motivo Cancelacion',
            'Fecha Registro',
        ]);

        foreach ($citas as $c) {
            $pacienteNombre = $c->paciente
                ? trim(($c->paciente->nombres ?? '').' '.($c->paciente->apellidos ?? ''))
                : 'N/A';
            $pacienteCi = $c->paciente->ci ?? 'N/A';
            $medicoNombre = $c->medico?->user?->name ?? 'N/A';
            $especialidadNombre = $c->medico?->especialidad?->nombre ?? 'N/A';

            fputcsv($output, [
                $c->id,
                $c->codigo_ficha,
                $c->fecha ? Carbon::parse($c->fecha)->format('Y-m-d') : '',
                $c->hora ?? '',
                ucfirst(str_replace('_', ' ', (string) $c->estado)),
                $especialidadNombre,
                $medicoNombre,
                $pacienteNombre,
                $pacienteCi,
                $c->es_cupo_recuperado ? 'SI' : 'NO',
                $c->motivo_cancelacion ?? '',
                $c->created_at ? Carbon::parse($c->created_at)->format('Y-m-d H:i') : '',
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return (string) $csv;
    }

    /**
     * Aplica filtros a la consulta de citas.
     *
     * @param  array<string, mixed>  $filtros
     */
    protected function aplicarFiltros(Builder $query, array $filtros): Builder
    {
        if (! empty($filtros['fecha_inicio'])) {
            $query->whereDate('fecha', '>=', $filtros['fecha_inicio']);
        }
        if (! empty($filtros['fecha_fin'])) {
            $query->whereDate('fecha', '<=', $filtros['fecha_fin']);
        }
        if (! empty($filtros['especialidad_id'])) {
            $query->whereHas('medico', function (Builder $q) use ($filtros) {
                $q->where('especialidad_id', $filtros['especialidad_id']);
            });
        }
        if (! empty($filtros['medico_id'])) {
            $query->where('medico_id', $filtros['medico_id']);
        }
        if (! empty($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        }

        return $query;
    }

    /**
     * Desglose de citas por estado para visualización gráfica.
     *
     * @param  array<string, mixed>  $filtros
     * @return array<string, int>
     */
    public function obtenerDesglosePorEstado(array $filtros = []): array
    {
        $query = $this->aplicarFiltros(Cita::query(), $filtros);

        $estados = [
            'programada' => 0,
            'confirmada' => 0,
            'en_espera' => 0,
            'en_atencion' => 0,
            'atendida' => 0,
            'cancelada' => 0,
            'reprogramada' => 0,
            'inasistencia' => 0,
            'postergada' => 0,
        ];

        $counts = (clone $query)->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado')
            ->toArray();

        foreach ($counts as $estado => $count) {
            $estados[$estado] = (int) $count;
        }

        return $estados;
    }

    /**
     * Desglose de citas por especialidad médica.
     *
     * @param  array<string, mixed>  $filtros
     * @return array<string, int>
     */
    public function obtenerDesglosePorEspecialidad(array $filtros = []): array
    {
        $query = $this->aplicarFiltros(Cita::query(), $filtros)
            ->join('medicos', 'citas.medico_id', '=', 'medicos.id')
            ->join('especialidades', 'medicos.especialidad_id', '=', 'especialidades.id')
            ->selectRaw('especialidades.nombre as especialidad, COUNT(citas.id) as total')
            ->groupBy('especialidades.nombre')
            ->orderBy('total', 'desc')
            ->pluck('total', 'especialidad')
            ->toArray();

        return array_map('intval', $query);
    }
}
