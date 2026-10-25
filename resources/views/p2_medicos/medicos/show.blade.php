@extends('layouts.app')

@section('content')
<div style="max-width: 900px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <div style="width: 54px; height: 54px; border-radius: 50%; background: {{ $medico->especialidad?->color ?? '#0D7C7C' }}20; color: {{ $medico->especialidad?->color ?? '#0D7C7C' }}; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1.25rem;">
                {{ strtoupper(substr($medico->user?->name ?? 'Dr', 0, 2)) }}
            </div>
            <div>
                <h1 style="font-size: var(--font-2xl); font-weight: 700; color: var(--color-primary); line-height: 1.2;">
                    {{ $medico->user?->name }}
                </h1>
                <div style="display: flex; gap: 0.5rem; align-items: center; margin-top: 0.25rem;">
                    <span class="badge" style="background: {{ $medico->especialidad?->color ?? '#0D7C7C' }}15; color: {{ $medico->especialidad?->color ?? '#0D7C7C' }}; font-weight: 600;">
                        {{ $medico->especialidad?->nombre }}
                    </span>
                    <span style="font-size: 0.8125rem; color: var(--color-text-muted); font-family: monospace;">
                        Matrícula: {{ $medico->nro_registro_medico }}
                    </span>
                </div>
            </div>
        </div>

        <div style="display: flex; gap: 0.5rem;">
            <a href="{{ route('horarios.edit', $medico) }}" class="btn btn-primary">
                ⚙️ Configurar Horario (CU5)
            </a>
            <a href="{{ route('medicos.edit', $medico) }}" class="btn btn-secondary">
                Editar Datos
            </a>
            <a href="{{ route('medicos.index') }}" class="btn btn-secondary">
                ← Volver
            </a>
        </div>
    </div>

    <!-- Grid de Info y Horario -->
    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 1.5rem;">
        <!-- Tarjeta de Contacto y Bio -->
        <div class="card" style="height: fit-content;">
            <h3 style="font-size: var(--font-md); font-weight: 700; color: var(--color-primary); margin-bottom: 1rem; border-bottom: 1px solid var(--color-border); padding-bottom: 0.5rem;">
                Datos de Contacto
            </h3>

            <div style="display: flex; flex-direction: column; gap: 0.75rem; font-size: 0.875rem;">
                <div>
                    <span style="color: var(--color-text-muted); display: block; font-size: 0.75rem;">Cédula de Identidad</span>
                    <strong>{{ $medico->user?->ci }}</strong>
                </div>
                <div>
                    <span style="color: var(--color-text-muted); display: block; font-size: 0.75rem;">Correo Electrónico</span>
                    <strong>{{ $medico->user?->email }}</strong>
                </div>
                <div>
                    <span style="color: var(--color-text-muted); display: block; font-size: 0.75rem;">Teléfono / Celular</span>
                    <strong>{{ $medico->telefono_contacto ?: ($medico->user?->telefono ?? 'No registrado') }}</strong>
                </div>
                <div>
                    <span style="color: var(--color-text-muted); display: block; font-size: 0.75rem;">Estado Clínico</span>
                    @if($medico->estado === 'activo')
                        <span class="badge badge-success">Activo en Consultorio</span>
                    @else
                        <span class="badge" style="background: #E2E8F0; color: #718096;">Inactivo</span>
                    @endif
                </div>
                @if($medico->biografia)
                    <div style="margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px dashed var(--color-border);">
                        <span style="color: var(--color-text-muted); display: block; font-size: 0.75rem;">Biografía</span>
                        <p style="font-size: 0.8125rem; color: var(--color-text); line-height: 1.4; margin-top: 0.25rem;">
                            {{ $medico->biografia }}
                        </p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Horario Semanal Configurado -->
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; border-bottom: 1px solid var(--color-border); padding-bottom: 0.5rem;">
                <h3 style="font-size: var(--font-md); font-weight: 700; color: var(--color-primary);">
                    Horario de Atención Semanal (CU5)
                </h3>
                <span class="badge" style="background: #EBF8FF; color: #2B6CB0; font-size: 0.75rem;">Slots fijos de 15 min</span>
            </div>

            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.875rem; text-align: left;">
                    <thead>
                        <tr style="background: #F8FAFC; border-bottom: 1px solid var(--color-border); color: var(--color-text-muted); font-size: 0.75rem; text-transform: uppercase;">
                            <th style="padding: 0.65rem 0.875rem;">Día</th>
                            <th style="padding: 0.65rem 0.875rem;">Jornada</th>
                            <th style="padding: 0.65rem 0.875rem;">Descanso (1 Hora Fija)</th>
                            <th style="padding: 0.65rem 0.875rem;">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($medico->horarios as $h)
                            <tr style="border-bottom: 1px solid var(--color-border);">
                                <td style="padding: 0.75rem 0.875rem; font-weight: 600; color: var(--color-primary);">
                                    {{ $h->nombre_dia }}
                                </td>
                                <td style="padding: 0.75rem 0.875rem;">
                                    {{ substr($h->hora_inicio, 0, 5) }} – {{ substr($h->hora_fin, 0, 5) }}
                                </td>
                                <td style="padding: 0.75rem 0.875rem; color: #DD6B20; font-weight: 500;">
                                    ☕ {{ substr($h->descanso_inicio, 0, 5) }} – {{ substr($h->descanso_fin, 0, 5) }} (1h)
                                </td>
                                <td style="padding: 0.75rem 0.875rem;">
                                    <span class="badge badge-success">Activo</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" style="padding: 1.5rem; text-align: center; color: var(--color-text-muted);">
                                    No se ha configurado la jornada semanal para este médico.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div style="margin-top: 1.25rem; display: flex; justify-content: flex-end;">
                <a href="{{ route('agenda.show', ['medico' => $medico->id]) }}" class="btn btn-secondary" style="font-size: 0.8125rem;">
                    📅 Ver Agenda de Turnos en Calendario (CU12) →
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
