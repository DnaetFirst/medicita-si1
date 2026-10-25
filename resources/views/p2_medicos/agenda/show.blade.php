@extends('layouts.app')

@section('content')
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 style="font-size: var(--font-2xl); font-weight: 700; color: var(--color-primary); margin-bottom: 0.25rem;">
            Consulta de Agenda Médica (CU12)
        </h1>
        <p style="color: var(--color-text-muted); font-size: var(--font-sm);">
            Visualización en tiempo real de slots de 15 minutos, descansos y pacientes citados
        </p>
    </div>

    <!-- Navegador de Fechas -->
    <div style="display: flex; align-items: center; gap: 0.5rem; background: white; padding: 0.35rem 0.65rem; border: 1px solid var(--color-border); border-radius: var(--border-radius-sm);">
        <a href="{{ route('agenda.show', ['medico_id' => $medico?->id, 'fecha' => $fechaCarbon->copy()->subDay()->toDateString()]) }}" class="btn btn-secondary" style="padding: 0.25rem 0.5rem;" title="Día Anterior">
            ←
        </a>
        <form method="GET" action="{{ route('agenda.show') }}" style="display: inline-flex; align-items: center; gap: 0.5rem;">
            <input type="hidden" name="medico_id" value="{{ $medico?->id }}">
            <input type="date" name="fecha" value="{{ $fecha }}" onchange="this.form.submit()" class="form-input" style="padding: 0.25rem 0.5rem; font-weight: 600; font-size: 0.8125rem;">
        </form>
        <a href="{{ route('agenda.show', ['medico_id' => $medico?->id, 'fecha' => $fechaCarbon->copy()->addDay()->toDateString()]) }}" class="btn btn-secondary" style="padding: 0.25rem 0.5rem;" title="Día Siguiente">
            →
        </a>
        <a href="{{ route('agenda.show', ['medico_id' => $medico?->id, 'fecha' => today()->toDateString()]) }}" class="btn btn-primary" style="padding: 0.25rem 0.65rem; font-size: 0.75rem;">
            Hoy
        </a>
    </div>
</div>

<!-- Selector de Médico y Resumen -->
<div class="card" style="margin-bottom: 1.5rem; padding: 1.25rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        @if(!auth()->user()->isMedico())
            <form method="GET" action="{{ route('agenda.show') }}" style="display: flex; align-items: center; gap: 0.75rem; flex: 1; max-width: 400px;">
                <input type="hidden" name="fecha" value="{{ $fecha }}">
                <label class="form-label" style="margin-bottom: 0; white-space: nowrap; font-size: 0.8125rem;">Médico:</label>
                <select name="medico_id" class="form-input" onchange="this.form.submit()" style="width: 100%;">
                    @foreach($medicos as $m)
                        <option value="{{ $m->id }}" {{ $medico?->id == $m->id ? 'selected' : '' }}>
                            {{ $m->user?->name }} ({{ $m->especialidad?->nombre }})
                        </option>
                    @endforeach
                </select>
            </form>
        @else
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <div style="width: 42px; height: 42px; border-radius: 50%; background: var(--color-secondary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700;">
                    {{ strtoupper(substr($medico?->user?->name ?? 'Dr', 0, 2)) }}
                </div>
                <div>
                    <h2 style="font-size: var(--font-lg); font-weight: 700; color: var(--color-primary); line-height: 1.2;">
                        {{ $medico?->user?->name }}
                    </h2>
                    <span class="badge" style="background: {{ $medico?->especialidad?->color ?? '#0D7C7C' }}15; color: {{ $medico?->especialidad?->color ?? '#0D7C7C' }}; font-size: 0.75rem;">
                        {{ $medico?->especialidad?->nombre }}
                    </span>
                </div>
            </div>
        @endif

        <!-- KPIs del Día -->
        <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
            <div style="background: #F8FAFC; border: 1px solid var(--color-border); border-radius: 8px; padding: 0.5rem 0.875rem; text-align: center;">
                <span style="font-size: 0.7rem; color: var(--color-text-muted); display: block;">Total Slots</span>
                <strong style="font-size: 1.125rem; color: var(--color-primary);">{{ $stats['total'] }}</strong>
            </div>
            <div style="background: #F0FFF4; border: 1px solid #C6F6D5; border-radius: 8px; padding: 0.5rem 0.875rem; text-align: center;">
                <span style="font-size: 0.7rem; color: #22543D; display: block;">Disponibles</span>
                <strong style="font-size: 1.125rem; color: var(--color-success);">{{ $stats['disponibles'] }}</strong>
            </div>
            <div style="background: #EBF8FF; border: 1px solid #BEE3F8; border-radius: 8px; padding: 0.5rem 0.875rem; text-align: center;">
                <span style="font-size: 0.7rem; color: #2A4365; display: block;">Reservados</span>
                <strong style="font-size: 1.125rem; color: #3182CE;">{{ $stats['reservados'] }}</strong>
            </div>
            <div style="background: #FFFAF0; border: 1px solid #FEEBC8; border-radius: 8px; padding: 0.5rem 0.875rem; text-align: center;">
                <span style="font-size: 0.7rem; color: #744210; display: block;">Descanso (1h)</span>
                <strong style="font-size: 1.125rem; color: #DD6B20;">{{ $stats['descanso'] }}</strong>
            </div>
            @if($stats['bloqueados'] > 0)
                <div style="background: #FFF5F5; border: 1px solid #FED7D7; border-radius: 8px; padding: 0.5rem 0.875rem; text-align: center;">
                    <span style="font-size: 0.7rem; color: #742A2A; display: block;">Bloqueados</span>
                    <strong style="font-size: 1.125rem; color: var(--color-danger);">{{ $stats['bloqueados'] }}</strong>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Cuadrícula de Slots de 15 Minutos -->
<div class="card" style="padding: 1.5rem;">
    <h3 style="font-size: var(--font-md); font-weight: 700; color: var(--color-primary); margin-bottom: 1.25rem; border-bottom: 1px solid var(--color-border); padding-bottom: 0.5rem;">
        Turnos del {{ $fechaCarbon->translatedFormat('l, d \d\e F \d\e Y') }}
    </h3>

    @if($slots->isEmpty())
        <div style="padding: 3rem; text-align: center; color: var(--color-text-muted);">
            <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">📅</div>
            <h4 style="font-weight: 700; color: var(--color-primary); margin-bottom: 0.25rem;">El médico no atiende en este día</h4>
            <p style="font-size: 0.875rem;">Revisa la jornada semanal configurada en el perfil del profesional.</p>
        </div>
    @else
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); gap: 0.875rem;">
            @foreach($slots as $slot)
                @php
                    $isDisp = $slot->estado === 'disponible';
                    $isRes = $slot->estado === 'reservado';
                    $isDesc = $slot->estado === 'descanso';
                    $isBloq = $slot->estado === 'bloqueado';
                @endphp

                <div style="padding: 0.875rem; border-radius: 10px; border: 1px solid; 
                    @if($isDisp) background: #F0FFF4; border-color: #9AE6B4;
                    @elseif($isRes) background: #EBF8FF; border-color: #90CDF4;
                    @elseif($isDesc) background: #FFFAF0; border-color: #FBD38D;
                    @else background: #EDF2F7; border-color: #CBD5E0; @endif">

                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
                        <span style="font-weight: 700; font-size: 0.95rem; font-family: monospace; 
                            @if($isDisp) color: var(--color-success);
                            @elseif($isRes) color: #2B6CB0;
                            @elseif($isDesc) color: #DD6B20;
                            @else color: #718096; @endif">
                            {{ substr($slot->hora_inicio, 0, 5) }} – {{ substr($slot->hora_fin, 0, 5) }}
                        </span>

                        @if($isDisp)
                            <span class="badge badge-success" style="font-size: 0.65rem;">Libre</span>
                        @elseif($isRes)
                            <span class="badge badge-info" style="font-size: 0.65rem;">Cita</span>
                        @elseif($isDesc)
                            <span class="badge badge-warning" style="font-size: 0.65rem;">Almuerzo</span>
                        @else
                            <span class="badge badge-danger" style="font-size: 0.65rem;">Bloqueado</span>
                        @endif
                    </div>

                    @if($isRes && $slot->cita)
                        <div style="font-size: 0.8125rem; font-weight: 600; color: #2D3748; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            👤 {{ $slot->cita->paciente?->nombre_completo ?? 'Paciente' }}
                        </div>
                        <div style="font-size: 0.7rem; color: var(--color-text-muted);">
                            Ficha: {{ $slot->cita->codigo_ficha }}
                        </div>
                    @elseif($isDesc)
                        <div style="font-size: 0.75rem; color: #744210; font-weight: 500;">
                            ☕ 1h Descanso Médico
                        </div>
                    @elseif($isBloq)
                        <div style="font-size: 0.75rem; color: var(--color-text-muted);">
                            🔒 No Disponible
                        </div>
                    @else
                        <div style="font-size: 0.75rem; color: var(--color-success); font-weight: 500;">
                            ✓ Apto para Agendar
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
