@extends('layouts.app')

@section('content')
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 style="font-size: var(--font-2xl); font-weight: 700; color: var(--color-primary); margin-bottom: 0.25rem;">
            Horarios de Atención y Slots (CU5)
        </h1>
        <p style="color: var(--color-text-muted); font-size: var(--font-sm);">
            Configuración de jornadas médicas semanales con slots fijos de 15 min y 1 hora de descanso
        </p>
    </div>
    <div style="font-size: var(--font-xs); background: #E6F7F9; color: var(--color-secondary); padding: 0.5rem 0.875rem; border-radius: var(--border-radius-pill); font-weight: 600;">
        ⏱ Regla de Negocio: Slots 15m · Descanso 1h Fija
    </div>
</div>

<div class="card" style="padding: 0; overflow: hidden;">
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.875rem;">
            <thead>
                <tr style="background: #F8FAFC; border-bottom: 1px solid var(--color-border); color: var(--color-text-muted); font-size: 0.75rem; text-transform: uppercase;">
                    <th style="padding: 0.875rem 1.25rem;">Médico / Especialidad</th>
                    <th style="padding: 0.875rem 1.25rem;">Días Hábiles Activos</th>
                    <th style="padding: 0.875rem 1.25rem;">Rango Horario Promedio</th>
                    <th style="padding: 0.875rem 1.25rem;">Descanso (Almuerzo)</th>
                    <th style="padding: 0.875rem 1.25rem; text-align: right;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($medicos as $med)
                    <tr style="border-bottom: 1px solid var(--color-border);" onmouseover="this.style.background='#F8FAFC'" onmouseout="this.style.background='white'">
                        <td style="padding: 1rem 1.25rem;">
                            <div style="font-weight: 600; color: var(--color-primary);">{{ $med->user?->name }}</div>
                            <span class="badge" style="background: {{ $med->especialidad?->color ?? '#0D7C7C' }}15; color: {{ $med->especialidad?->color ?? '#0D7C7C' }}; font-size: 0.75rem;">
                                {{ $med->especialidad?->nombre }}
                            </span>
                        </td>
                        <td style="padding: 1rem 1.25rem;">
                            <div style="display: flex; gap: 0.25rem; flex-wrap: wrap;">
                                @php
                                    $diasLetras = [1 => 'L', 2 => 'M', 3 => 'M', 4 => 'J', 5 => 'V', 6 => 'S', 7 => 'D'];
                                    $diasActivos = $med->horarios->pluck('dia_semana')->toArray();
                                @endphp
                                @foreach($diasLetras as $num => $letra)
                                    <span style="width: 24px; height: 24px; border-radius: 4px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 600; background: {{ in_array($num, $diasActivos) ? 'var(--color-secondary)' : '#E2E8F0' }}; color: {{ in_array($num, $diasActivos) ? 'white' : '#A0AEC0' }};">
                                        {{ $letra }}
                                    </span>
                                @endforeach
                            </div>
                        </td>
                        <td style="padding: 1rem 1.25rem; color: var(--color-text);">
                            @if($med->horarios->isNotEmpty())
                                {{ substr($med->horarios->first()->hora_inicio, 0, 5) }} – {{ substr($med->horarios->first()->hora_fin, 0, 5) }}
                            @else
                                <span style="color: var(--color-text-muted);">Sin horario</span>
                            @endif
                        </td>
                        <td style="padding: 1rem 1.25rem; color: #DD6B20; font-weight: 500;">
                            @if($med->horarios->isNotEmpty())
                                ☕ {{ substr($med->horarios->first()->descanso_inicio, 0, 5) }} – {{ substr($med->horarios->first()->descanso_fin, 0, 5) }} (1 hora)
                            @else
                                —
                            @endif
                        </td>
                        <td style="padding: 1rem 1.25rem; text-align: right;">
                            <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                                <a href="{{ route('horarios.edit', $med) }}" class="btn btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.75rem;">
                                    ⚙️ Modificar Horario
                                </a>

                                <form action="{{ route('horarios.generar', $med) }}" method="POST" style="display: inline;">
                                    @csrf
                                    <button type="submit" class="btn btn-primary" style="padding: 0.35rem 0.65rem; font-size: 0.75rem;">
                                        Generar Slots (14d)
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="padding: 2.5rem; text-align: center; color: var(--color-text-muted);">
                            No hay médicos activos disponibles para configurar horarios.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
