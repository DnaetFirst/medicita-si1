@extends('layouts.app')

@section('content')
<div class="cycle-page">
    <div class="cycle-heading">
        <div>
            <h1>Orden de Laboratorio: {{ $orden->codigo_orden }}</h1>
            <p>Paciente: <strong>{{ $orden->paciente->nombre_completo }}</strong> · Solicitado por: Dr. {{ $orden->medico->user->name }}</p>
        </div>
        <div class="cycle-actions">
            @if(auth()->user()->rol?->slug === 'lab')
                <a class="btn btn-secondary" href="{{ route('laboratorio.ordenes.index') }}">Volver a la bandeja</a>
            @else
                <a class="btn btn-secondary" href="{{ route('historia.show', $orden->paciente) }}">Historia Clínica</a>
            @endif
        </div>
    </div>

    <!-- Información General -->
    <div class="card cycle-card">
        <div class="cycle-heading">
            <div>
                <h3>Estado actual: <span class="badge {{ match($orden->estado) { 'finalizada' => 'badge-success', 'en_proceso' => 'badge-info', 'cancelada' => 'badge-danger', default => 'badge-secondary' } }}">{{ ucfirst(str_replace('_', ' ', $orden->estado)) }}</span></h3>
                <small>Fecha de emisión: {{ $orden->created_at->format('d/m/Y H:i') }}</small>
            </div>
            @if(auth()->user()->rol?->slug === 'lab' && $orden->estado !== 'finalizada' && $orden->estado !== 'cancelada')
                <a class="btn btn-primary" href="{{ route('laboratorio.resultados.create', $orden) }}">Capturar nuevo resultado</a>
            @endif
        </div>

        @if($orden->observaciones)
            <div style="margin-top: 1rem;">
                <strong>Observaciones médicas:</strong>
                <p class="cycle-text">{{ $orden->observaciones }}</p>
            </div>
        @endif
    </div>

    <!-- Lista de Exámenes Solicitados -->
    <div class="card cycle-card">
        <h3>Exámenes Solicitados y Cobertura</h3>
        <table style="width: 100%; border-collapse: collapse; margin-top: 1rem;">
            <thead>
                <tr style="border-bottom: 2px solid var(--color-border); text-align: left;">
                    <th style="padding: 0.5rem;">Examen</th>
                    <th style="padding: 0.5rem;">Categoría</th>
                    <th style="padding: 0.5rem;">Estado</th>
                </tr>
            </thead>
            <tbody>
                @foreach($orden->examenes as $examen)
                    <tr style="border-bottom: 1px solid var(--color-border);">
                        <td style="padding: 0.75rem 0.5rem;"><strong>{{ $examen->nombre }}</strong> ({{ $examen->codigo }})</td>
                        <td style="padding: 0.75rem 0.5rem;">{{ $examen->categoria }}</td>
                        <td style="padding: 0.75rem 0.5rem;">
                            <span class="badge {{ match($examen->pivot->estado) { 'informado' => 'badge-success', 'en_proceso' => 'badge-info', default => 'badge-secondary' } }}">
                                {{ ucfirst(str_replace('_', ' ', $examen->pivot->estado)) }}
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- Resultados Emitidos -->
    <h2>Informes de Resultados ({{ $orden->resultados->count() }})</h2>
    @forelse($orden->resultados->sortByDesc('version') as $res)
        <article class="card cycle-card">
            <div class="cycle-heading">
                <div>
                    <h3>Informe: {{ $res->codigo_resultado }} (Versión {{ $res->version }})</h3>
                    <p>Autor: {{ $res->autor->name }} · {{ $res->created_at->format('d/m/Y H:i') }}</p>
                    @if($res->motivo_correccion)<p><strong>Motivo de corrección:</strong> {{ $res->motivo_correccion }}</p>@endif
                </div>
                <strong class="badge {{ match($res->estado) { 'publicado' => 'badge-success', 'validado' => 'badge-info', default => 'badge-warning' } }}">
                    {{ ucfirst($res->estado) }}
                </strong>
            </div>

            <!-- Tabla de Detalles -->
            <table style="width: 100%; border-collapse: collapse; margin-top: 1rem;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--color-border); font-size: 0.85rem; color: var(--color-text-muted); text-align: left;">
                        <th style="padding: 0.5rem;">Parámetro</th>
                        <th style="padding: 0.5rem;">Valor</th>
                        <th style="padding: 0.5rem;">Unidad</th>
                        <th style="padding: 0.5rem;">Referencia</th>
                        <th style="padding: 0.5rem;">Interpretación</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($res->detalles as $det)
                        <tr style="border-bottom: 1px solid var(--color-border); font-size: 0.9rem;">
                            <td style="padding: 0.5rem;">{{ $det->parametro }}</td>
                            <td style="padding: 0.5rem;"><strong>{{ $det->valor }}</strong></td>
                            <td style="padding: 0.5rem;">{{ $det->unidad_medida ?? '—' }}</td>
                            <td style="padding: 0.5rem;">{{ $det->rango_referencia ?? '—' }}</td>
                            <td style="padding: 0.5rem;">{{ $det->interpretacion ?? 'Normal' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="cycle-actions" style="margin-top: 1rem;">
                @if($res->isPublicado())
                    <a class="btn btn-secondary" href="{{ route('laboratorio.resultados.show', $res) }}">Ver informe completo</a>
                    @if($res->archivo_pdf_path)
                        <a class="btn btn-primary" href="{{ route('laboratorio.resultados.descargar', $res) }}">Descargar PDF Oficial</a>
                    @endif
                @endif

                @if(auth()->user()->rol?->slug === 'lab')
                    @if($res->isBorrador())
                        <form action="{{ route('laboratorio.resultados.validar', $res) }}" method="POST">
                            @csrf
                            <button class="btn btn-primary">Validar resultado</button>
                        </form>
                    @elseif($res->isValidado())
                        <form action="{{ route('laboratorio.resultados.publicar', $res) }}" method="POST">
                            @csrf
                            <button class="btn btn-success">Publicar resultado (Notifica a paciente)</button>
                        </form>
                    @endif
                @endif
            </div>
        </article>
    @empty
        <div class="card cycle-card">
            <p>Todavía no se han capturado resultados para esta orden.</p>
        </div>
    @endforelse
</div>
@endsection
