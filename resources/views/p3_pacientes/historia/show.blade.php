@extends('layouts.app')
@section('content')
<div class="cycle-page">
    <div class="cycle-heading"><div><h1>Historia clínica</h1><p>{{ $paciente->nombre_completo }} · {{ $historia?->nro_expediente ?? 'Expediente pendiente de apertura' }}</p></div><a class="btn btn-secondary" href="{{ route('historial.index', ['paciente_id' => $paciente->id]) }}">Historial de citas</a></div>
    <section class="card cycle-card"><h2>Resumen clínico</h2><div class="cycle-grid"><div><h3>Antecedentes</h3><p class="cycle-text">{{ $historia?->antecedentes ?? 'Sin registrar' }}</p></div><div><h3>Medicación habitual</h3><p class="cycle-text">{{ $historia?->medicacion_habitual ?? 'Sin registrar' }}</p></div></div></section>
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
