@extends('layouts.app')

@section('content')
<div class="cycle-page">
    <div class="cycle-heading">
        <div>
            <h1>Emitir Orden de Laboratorio (CU15)</h1>
            <p>Paciente: <strong>{{ $paciente->nombre_completo }}</strong> (CI: {{ $paciente->ci }})</p>
        </div>
        <a class="btn btn-secondary" href="{{ route('historia.show', $paciente) }}">Volver a Historia Clínica</a>
    </div>

    <form class="card cycle-card" method="POST" action="{{ route('laboratorio.ordenes.store', $paciente) }}">
        @csrf
        @if($cita_id)<input type="hidden" name="cita_id" value="{{ $cita_id }}">@endif
        @if($atencion_id)<input type="hidden" name="atencion_medica_id" value="{{ $atencion_id }}">@endif
        <input type="hidden" name="operacion_id" value="{{ old('operacion_id', (string) Str::uuid()) }}">

        <h2>Selección de Exámenes Clínicos</h2>
        <p style="color: var(--color-text-muted); margin-bottom: 1rem;">Selecciona los análisis requeridos para el paciente:</p>

        <div class="cycle-grid" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));">
            @foreach($examenes as $examen)
                <label class="card" style="padding: 1rem; cursor: pointer; display: flex; align-items: flex-start; gap: 0.75rem;">
                    <input type="checkbox" name="examenes[]" value="{{ $examen->id }}" @checked(is_array(old('examenes')) && in_array($examen->id, old('examenes'))) style="margin-top: 0.25rem;">
                    <div>
                        <strong>{{ $examen->nombre }}</strong>
                        <div style="font-size: 0.8rem; color: var(--color-text-muted);">{{ $examen->categoria }} · Código: {{ $examen->codigo }}</div>
                        @if($examen->descripcion)<div style="font-size: 0.8rem; margin-top: 0.25rem;">{{ $examen->descripcion }}</div>@endif
                    </div>
                </label>
            @endforeach
        </div>

        <label style="margin-top: 1.5rem;">Indicaciones clínicas y observaciones para el laboratorio
            <textarea class="form-input" name="observaciones" rows="3" placeholder="Ej: Paciente en ayunas, descartar anemia ferropénica...">{{ old('observaciones') }}</textarea>
        </label>

        <div class="cycle-actions" style="margin-top: 1.5rem;">
            <button class="btn btn-primary">Emitir Orden de Laboratorio</button>
            <a class="btn btn-secondary" href="{{ route('historia.show', $paciente) }}">Cancelar</a>
        </div>
    </form>
</div>
@endsection
