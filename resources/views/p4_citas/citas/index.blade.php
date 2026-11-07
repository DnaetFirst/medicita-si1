@extends('layouts.app')

@section('content')
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 style="font-size: var(--font-2xl); font-weight: 700; color: var(--color-primary); margin-bottom: 0.25rem;">
            Control de Citas Médicas y Recepción (CU10)
        </h1>
        <p style="color: var(--color-text-muted); font-size: var(--font-sm);">
            Gestión de turnos, check-in en sala de espera, transiciones de atención médica y cancelaciones
        </p>
    </div>
    @unless(auth()->user()->isMedico())<a href="{{ route('citas.nueva') }}" class="btn btn-primary" style="box-shadow: 0 4px 10px rgba(13, 124, 124, 0.2);">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v8"/><path d="M8 12h8"/></svg>
        Solicitar Ficha / Nueva Cita (CU8)
    </a>@endunless
</div>

<!-- KPIs del Día -->
<div class="grid-4" style="margin-bottom: 1.5rem;">
    <div class="card" style="margin-bottom: 0; padding: 1rem;">
        <span style="font-size: 0.75rem; color: var(--color-text-muted); font-weight: 600; text-transform: uppercase;">Total Citas Hoy</span>
        <div style="font-size: 1.75rem; font-weight: 700; color: var(--color-primary);">{{ $kpis['total_hoy'] }}</div>
    </div>
    <div class="card" style="margin-bottom: 0; padding: 1rem;">
        <span style="font-size: 0.75rem; color: var(--color-text-muted); font-weight: 600; text-transform: uppercase;">Por Llegar (Programadas)</span>
        <div style="font-size: 1.75rem; font-weight: 700; color: var(--color-info);">{{ $kpis['programadas'] }}</div>
    </div>
    <div class="card" style="margin-bottom: 0; padding: 1rem;">
        <span style="font-size: 0.75rem; color: var(--color-text-muted); font-weight: 600; text-transform: uppercase;">En Espera (Check-in)</span>
        <div style="font-size: 1.75rem; font-weight: 700; color: var(--color-secondary);">{{ $kpis['confirmadas_checkin'] }}</div>
    </div>
    <div class="card" style="margin-bottom: 0; padding: 1rem;">
        <span style="font-size: 0.75rem; color: var(--color-text-muted); font-weight: 600; text-transform: uppercase;">Atendidas con Éxito</span>
        <div style="font-size: 1.75rem; font-weight: 700; color: var(--color-success);">{{ $kpis['atendidas'] }}</div>
    </div>
</div>

<!-- Filtros -->
<div class="card" style="padding: 1rem; margin-bottom: 1.5rem;">
    <form method="GET" action="{{ route('citas.index') }}" style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
        <div style="flex: 2; min-width: 220px;">
            <input type="text" name="search" class="form-input" placeholder="Buscar por código de ficha, paciente o CI..." value="{{ $filters['search'] ?? '' }}" style="width: 100%;">
        </div>

        <div style="flex: 1; min-width: 150px;">
            <input type="date" name="fecha" class="form-input" value="{{ $filters['fecha'] ?? '' }}" style="width: 100%;">
        </div>

        <div style="flex: 1; min-width: 160px;">
            <select name="especialidad_id" class="form-input" style="width: 100%;">
                <option value="">Todas las Especialidades</option>
                @foreach($especialidades as $esp)
                    <option value="{{ $esp->id }}" {{ ($filters['especialidad_id'] ?? '') == $esp->id ? 'selected' : '' }}>
                        {{ $esp->nombre }}
                    </option>
                @endforeach
            </select>
        </div>

        @unless(auth()->user()->isMedico())
        <div style="flex: 1; min-width: 160px;">
            <select name="medico_id" class="form-input" style="width: 100%;">
                <option value="">Todos los Médicos</option>
                @foreach($medicos as $med)
                    <option value="{{ $med->id }}" {{ ($filters['medico_id'] ?? '') == $med->id ? 'selected' : '' }}>
                        {{ $med->user?->name }}
                    </option>
                @endforeach
            </select>
        </div>
        @endunless

        <div style="flex: 1; min-width: 140px;">
            <select name="estado" class="form-input" style="width: 100%;">
                <option value="">Todos los Estados</option>
                <option value="programada" {{ ($filters['estado'] ?? '') === 'programada' ? 'selected' : '' }}>Programada</option>
                <option value="confirmada" {{ ($filters['estado'] ?? '') === 'confirmada' ? 'selected' : '' }}>Confirmada</option><option value="en_espera" @selected(request("estado") === "en_espera")>En espera</option><option value="postergada" @selected(request("estado") === "postergada")>Postergada</option>
                <option value="en_atencion" {{ ($filters['estado'] ?? '') === 'en_atencion' ? 'selected' : '' }}>En Consulta</option>
                <option value="atendida" {{ ($filters['estado'] ?? '') === 'atendida' ? 'selected' : '' }}>Atendida</option>
                <option value="cancelada" {{ ($filters['estado'] ?? '') === 'cancelada' ? 'selected' : '' }}>Cancelada</option>
                <option value="inasistencia" {{ ($filters['estado'] ?? '') === 'inasistencia' ? 'selected' : '' }}>No Asistió</option>
            </select>
        </div>

        <button type="submit" class="btn btn-secondary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            Filtrar
        </button>

        @if(!empty($filters['search']) || !empty($filters['fecha']) || !empty($filters['estado']) || !empty($filters['medico_id']) || !empty($filters['especialidad_id']))
            <a href="{{ route('citas.index') }}" class="btn btn-secondary" style="color: var(--color-text-muted);">
                Limpiar
            </a>
        @endif
    </form>
</div>

<!-- Tabla de Citas -->
<div class="card" style="padding: 0; overflow: hidden;">
    <div style="overflow-x: auto;">
        <table class="table" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: var(--color-surface-hover); text-align: left; border-bottom: 1px solid var(--color-border);">
                    <th style="padding: 0.85rem 1rem; font-size: 0.8rem; font-weight: 600; color: var(--color-text-muted);">CÓDIGO FICHA</th>
                    <th style="padding: 0.85rem 1rem; font-size: 0.8rem; font-weight: 600; color: var(--color-text-muted);">PACIENTE</th>
                    <th style="padding: 0.85rem 1rem; font-size: 0.8rem; font-weight: 600; color: var(--color-text-muted);">MÉDICO / ESPECIALIDAD</th>
                    <th style="padding: 0.85rem 1rem; font-size: 0.8rem; font-weight: 600; color: var(--color-text-muted);">FECHA & TURNO (15m)</th>
                    <th style="padding: 0.85rem 1rem; font-size: 0.8rem; font-weight: 600; color: var(--color-text-muted);">ESTADO</th>
                    <th style="padding: 0.85rem 1rem; font-size: 0.8rem; font-weight: 600; color: var(--color-text-muted); text-align: right;">CONTROL DE ASISTENCIA</th>
                </tr>
            </thead>
            <tbody>
                @forelse($citas as $cita)
                    <tr style="border-bottom: 1px solid var(--color-border); transition: background 0.15s ease;">
                        <td style="padding: 0.85rem 1rem;">
                            <a href="{{ route('citas.show', $cita) }}" style="font-family: monospace; font-weight: 700; color: var(--color-primary); text-decoration: none; font-size: 0.95rem;">
                                {{ $cita->codigo_ficha }}
                            </a>
                            @if($cita->reprogramada_de_id)
                                <div style="font-size: 0.7rem; color: var(--color-secondary); font-weight: 600;">(Reprogramada)</div>
                            @endif
                        </td>
                        <td style="padding: 0.85rem 1rem;">
                            <div style="font-weight: 600;">{{ $cita->paciente?->nombre_completo }}</div>
                            <div style="font-size: 0.75rem; color: var(--color-text-muted);">CI: {{ $cita->paciente?->ci }} &bull; Cel: {{ $cita->paciente?->telefono }}</div>
                        </td>
                        <td style="padding: 0.85rem 1rem;">
                            <div style="font-weight: 500;">{{ $cita->medico?->user?->name }}</div>
                            <div style="font-size: 0.75rem; color: var(--color-text-muted);">{{ $cita->medico?->especialidad?->nombre }}</div>
                        </td>
                        <td style="padding: 0.85rem 1rem;">
                            <div style="font-weight: 600; color: var(--color-primary);">
                                {{ $cita->fecha ? $cita->fecha->format('d/m/Y') : '' }}
                            </div>
                            <div style="font-size: 0.8rem; font-weight: 700; color: var(--color-secondary);">
                                {{ substr($cita->hora, 0, 5) }} hrs
                            </div>
                        </td>
                        <td style="padding: 0.85rem 1rem;">
                            @php
                                $badgeClass = match($cita->estado) {
                                    'programada' => 'badge-info',
                                    'confirmada' => 'badge-primary',
                                    'en_atencion' => 'badge-warning',
                                    'atendida' => 'badge-success',
                                    'cancelada' => 'badge-danger',
                                    'inasistencia' => 'badge-danger',
                                    default => 'badge-secondary',
                                };
                                $estadoLabel = match($cita->estado) {
                                    'programada' => 'Programada',
                                    'confirmada' => 'Confirmada', 'en_espera' => 'En espera', 'postergada' => 'Postergada',
                                    'en_atencion' => 'En Consulta',
                                    'atendida' => 'Atendida',
                                    'cancelada' => 'Cancelada',
                                    'inasistencia' => 'No Asistió',
                                    default => ucfirst($cita->estado),
                                };
                            @endphp
                            <span class="badge {{ $badgeClass }}">
                                {{ $estadoLabel }}
                            </span>
                            @if($cita->hora_checkin)
                                <div style="font-size: 0.7rem; color: var(--color-text-muted); margin-top: 0.2rem;">
                                    Llegada: {{ $cita->hora_checkin->format('H:i') }}
                                </div>
                            @endif
                        </td>
                        <td style="padding: 0.85rem 1rem; text-align: right;">
                            <div style="display: flex; justify-content: flex-end; gap: 0.4rem; flex-wrap: wrap;">
                                <!-- Botón Comprobante / Ficha -->
                                <a href="{{ route('citas.show', $cita) }}" class="btn btn-sm btn-secondary" title="Ver Comprobante">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                </a>


                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="padding: 3rem; text-align: center; color: var(--color-text-muted);">
                            No hay citas médicas registradas que coincidan con los criterios seleccionados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($citas->hasPages())
        <div style="padding: 1rem; border-top: 1px solid var(--color-border);">
            {{ $citas->links() }}
        </div>
    @endif
</div>


@endsection
