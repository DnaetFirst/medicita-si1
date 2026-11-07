@extends('layouts.app')

@section('content')
<div class="cycle-page">
    <div class="cycle-heading">
        <div>
            <h1>Informe de Laboratorio: {{ $resultado->codigo_resultado }}</h1>
            <p>Orden: <strong>{{ $resultado->orden->codigo_orden }}</strong> · Paciente: <strong>{{ $resultado->orden->paciente->nombre_completo }}</strong></p>
        </div>
        <div class="cycle-actions">
            @if($resultado->isPublicado() || auth()->user()->rol?->slug === 'lab' || auth()->user()->isAdmin())
                <a class="btn btn-primary" href="{{ route('laboratorio.resultados.descargar', $resultado) }}" style="display: inline-flex; align-items: center; gap: 0.5rem;">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.5L19 9.5V19a2 2 0 01-2 2z"/></svg>
                    Descargar PDF Oficial
                </a>
            @endif
            @if(auth()->user()->isPaciente())
                <a class="btn btn-secondary" href="{{ route('laboratorio.mis_resultados') }}">Mis Resultados</a>
            @else
                <a class="btn btn-secondary" href="{{ route('laboratorio.ordenes.show', $resultado->orden) }}">Volver a la Orden</a>
            @endif
        </div>
    </div>

    <!-- Metadatos de Publicación -->
    <div class="card cycle-card">
        <div class="cycle-grid">
            <div>
                <strong>Médico Solicitante:</strong>
                <p>Dr. {{ $resultado->orden->medico->user->name }} ({{ $resultado->orden->medico->especialidad->nombre }})</p>
            </div>
            <div>
                <strong>Validado por:</strong>
                <p>{{ $resultado->validadoPor?->name ?? 'Laboratorio Clínico' }}</p>
            </div>
            <div>
                <strong>Fecha de Publicación:</strong>
                <p>{{ $resultado->fecha_publicacion?->format('d/m/Y H:i') ?? '—' }}</p>
            </div>
            <div>
                <strong>Versión:</strong>
                <p>Versión {{ $resultado->version }}</p>
            </div>
        </div>
    </div>

    <!-- Resultados Cuantitativos -->
    <div class="card cycle-card">
        <h2>Parámetros Clínicos Determinados</h2>
        <table style="width: 100%; border-collapse: collapse; margin-top: 1rem;">
            <thead>
                <tr style="border-bottom: 2px solid var(--color-border); text-align: left;">
                    <th style="padding: 0.75rem 0.5rem;">Prueba / Parámetro</th>
                    <th style="padding: 0.75rem 0.5rem;">Resultado</th>
                    <th style="padding: 0.75rem 0.5rem;">Unidad</th>
                    <th style="padding: 0.75rem 0.5rem;">Rango de Referencia</th>
                    <th style="padding: 0.75rem 0.5rem;">Interpretación</th>
                </tr>
            </thead>
            <tbody>
                @foreach($resultado->detalles as $det)
                    <tr style="border-bottom: 1px solid var(--color-border);">
                        <td style="padding: 0.75rem 0.5rem;">
                            <strong>{{ $det->parametro }}</strong>
                            <div style="font-size: 0.8rem; color: var(--color-text-muted);">{{ $det->examen->nombre }}</div>
                        </td>
                        <td style="padding: 0.75rem 0.5rem; font-size: 1.1rem; font-weight: 700;">{{ $det->valor }}</td>
                        <td style="padding: 0.75rem 0.5rem;">{{ $det->unidad_medida ?? '—' }}</td>
                        <td style="padding: 0.75rem 0.5rem;">{{ $det->rango_referencia ?? '—' }}</td>
                        <td style="padding: 0.75rem 0.5rem;">
                            <span class="badge {{ match($det->interpretacion) { 'Alto', 'Bajo' => 'badge-warning', 'Crítico' => 'badge-danger', default => 'badge-success' } }}">
                                {{ $det->interpretacion ?? 'Normal' }}
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if($resultado->observaciones)
            <div style="margin-top: 1.5rem; padding: 1rem; background: var(--color-surface-hover); border-radius: var(--border-radius-sm);">
                <strong>Observaciones técnicas del laboratorio:</strong>
                <p class="cycle-text" style="margin-top: 0.5rem;">{{ $resultado->observaciones }}</p>
            </div>
        @endif
    </div>
</div>
@endsection
