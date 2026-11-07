@extends('layouts.app')
@section('content')
<div class="cycle-page">
    <div class="cycle-heading">
        <div>
            <h1>Historia clínica</h1>
            <p>{{ $paciente->nombre_completo }} · {{ $historia?->nro_expediente ?? 'Expediente pendiente de apertura' }}</p>
        </div>
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
            <a class="btn btn-primary" href="{{ route('laboratorio.ordenes.create', $paciente) }}">🧪 Solicitar Análisis (CU15)</a>
            <a class="btn btn-secondary" href="{{ route('historial.index', ['paciente_id' => $paciente->id]) }}">Historial de citas</a>
        </div>
    </div>
    <section class="card cycle-card">
        <h2>Resumen clínico</h2>
        <div class="cycle-grid">
            <div>
                <h3>Antecedentes</h3>
                <p class="cycle-text">{{ $historia?->antecedentes ?? 'Sin registrar' }}</p>
            </div>
            <div>
                <h3>Medicación habitual</h3>
                <p class="cycle-text">{{ $historia?->medicacion_habitual ?? 'Sin registrar' }}</p>
            </div>
        </div>
    </section>

    <!-- Órdenes y Resultados de Laboratorio Clínico (CU15) -->
    <section class="card cycle-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; flex-wrap: wrap; gap: 0.5rem;">
            <h2>🧪 Órdenes y Resultados de Laboratorio (CU15)</h2>
            <a class="btn btn-primary" href="{{ route('laboratorio.ordenes.create', $paciente) }}" style="font-size: var(--font-sm);">+ Nueva Solicitud de Análisis</a>
        </div>
        @if(isset($ordenes) && $ordenes->isNotEmpty())
            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                @foreach($ordenes as $ord)
                    <div style="border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 0.85rem 1rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem; background: var(--color-bg-card);">
                        <div>
                            <strong>{{ $ord->codigo_orden }}</strong> · <span class="text-muted">{{ $ord->created_at->format('d/m/Y H:i') }}</span>
                            <div style="margin-top: 0.35rem; display: flex; gap: 0.35rem; flex-wrap: wrap;">
                                @foreach($ord->examenes as $ex)
                                    <span class="badge" style="background: var(--color-bg-secondary); font-size: 0.75rem;">{{ $ex->nombre }}</span>
                                @endforeach
                            </div>
                        </div>
                        <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                            <span class="badge {{ $ord->estado === 'finalizada' ? 'badge-success' : 'badge-info' }}">{{ ucfirst($ord->estado) }}</span>
                            <a class="btn btn-secondary" href="{{ route('laboratorio.ordenes.show', $ord) }}" style="font-size: var(--font-sm);">Ver Orden</a>
                            @foreach($ord->resultados->where('estado', 'publicado') as $resPub)
                                <a class="btn btn-primary" href="{{ route('laboratorio.resultados.descargar', $resPub) }}" style="font-size: var(--font-sm); display: inline-flex; align-items: center; gap: 0.35rem;">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.5L19 9.5V19a2 2 0 01-2 2z"/></svg>
                                    PDF ({{ $resPub->codigo_resultado }})
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <p class="cycle-text">No se registran órdenes de laboratorio para este paciente.</p>
        @endif
    </section>
    @if($citas->isNotEmpty())
    <section class="card cycle-card"><h2>Registrar atención</h2><p>Guardar conserva la cita en atención. El cierre se registra desde la ficha.</p>
        <form action="{{ route('historia.store', $paciente) }}" method="POST">@csrf
            <input type="hidden" name="formulario" value="nueva"><input type="hidden" name="version" value="{{ $historia?->version ?? 0 }}"><input type="hidden" name="operacion_id" value="{{ old('formulario') === 'nueva' ? old('operacion_id', (string) Str::uuid()) : Str::uuid() }}">
            <label>Cita en atención<select name="cita_id" class="form-input" required>@foreach($citas as $cita)<option value="{{ $cita->id }}" @selected(old('cita_id') == $cita->id)>{{ $cita->codigo_ficha }} · {{ $cita->fecha->format('d/m/Y') }} {{ substr($cita->hora, 0, 5) }}</option>@endforeach</select></label>
            <div class="cycle-grid">@include('p3_pacientes.historia.fields', ['nota' => null, 'formulario' => 'nueva'])</div><button class="btn btn-primary">Guardar nota</button>
        </form>
    </section>
    @else<p class="card cycle-card">Inicia la atención de una cita asignada para registrar una nota nueva.</p>@endif
    <h2>Evolución y correcciones</h2>
    @forelse($atenciones as $nota)
    <article class="card cycle-card">
        <h3>{{ $nota->es_correccion ? 'Corrección de nota #'.$nota->corrige_id : 'Nota de atención' }} #{{ $nota->id }}</h3><p>{{ $nota->created_at->format('d/m/Y H:i') }} · {{ $nota->autor->name }}</p>
        @if($nota->es_correccion)<p><strong>Motivo de corrección:</strong> {{ $nota->motivo_correccion }}</p>@endif
        <div class="cycle-grid">@foreach(['motivo_consulta' => 'Motivo', 'hallazgos' => 'Hallazgos', 'diagnostico' => 'Diagnóstico', 'tratamiento' => 'Tratamiento', 'indicaciones' => 'Indicaciones'] as $campo => $etiqueta)<div><h4>{{ $etiqueta }}</h4><p class="cycle-text">{{ $nota->$campo ?? 'Sin registrar' }}</p></div>@endforeach</div>
        @foreach($nota->derivaciones as $derivacion)<p><strong>Derivación a {{ $derivacion->especialidad->nombre }}:</strong> {{ $derivacion->motivo }}. {{ $derivacion->indicaciones }}</p>@endforeach
        @if($nota->cita->medico_id === auth()->user()->medico?->id)
        <details><summary>Corregir conservando la nota original</summary>
            <form action="{{ route('historia.store', $paciente) }}" method="POST">@csrf
                <input type="hidden" name="formulario" value="correccion-{{ $nota->id }}"><input type="hidden" name="cita_id" value="{{ $nota->cita_id }}"><input type="hidden" name="corrige_id" value="{{ $nota->id }}"><input type="hidden" name="version" value="{{ $historia->version }}"><input type="hidden" name="operacion_id" value="{{ Str::uuid() }}">
                <label>Motivo de corrección *<textarea class="form-input" name="motivo_correccion" required minlength="5" maxlength="1000">{{ old('formulario') === 'correccion-'.$nota->id ? old('motivo_correccion') : '' }}</textarea></label>
                <div class="cycle-grid">@include('p3_pacientes.historia.fields', ['formulario' => 'correccion-'.$nota->id])</div><button class="btn btn-primary">Guardar corrección</button>
            </form>
        </details>
        <details><summary>Emitir derivación</summary><form action="{{ route('historia.derivar', $nota) }}" method="POST" class="cycle-grid">@csrf<input type="hidden" name="operacion_id" value="{{ Str::uuid() }}"><label>Especialidad destino<select class="form-input" name="especialidad_id" required>@foreach($especialidades as $especialidad)<option value="{{ $especialidad->id }}">{{ $especialidad->nombre }}</option>@endforeach</select></label><label>Motivo *<textarea name="motivo" required maxlength="5000" class="form-input"></textarea></label><label>Indicaciones<textarea name="indicaciones" maxlength="5000" class="form-input"></textarea></label><div><p>La derivación no reserva una cita automáticamente.</p><button class="btn btn-primary">Emitir derivación</button></div></form></details>
        @endif
    </article>
    @empty<p class="card cycle-card">Todavía no se han registrado notas clínicas.</p>@endforelse
    {{ $atenciones->links() }}
</div>
@endsection
