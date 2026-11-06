@extends('layouts.app')

@section('content')
<div class="cycle-page">
    <div class="cycle-heading">
        <div>
            <h1>Capturar Resultados de Laboratorio (CU15)</h1>
            <p>Orden: <strong>{{ $orden->codigo_orden }}</strong> · Paciente: <strong>{{ $orden->paciente->nombre_completo }}</strong></p>
        </div>
        <a class="btn btn-secondary" href="{{ route('laboratorio.ordenes.show', $orden) }}">Volver a la Orden</a>
    </div>

    <form class="card cycle-card" method="POST" action="{{ route('laboratorio.resultados.store', $orden) }}" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="operacion_id" value="{{ old('operacion_id', (string) Str::uuid()) }}">

        <h2>Parámetros Clínicos</h2>
        <p style="color: var(--color-text-muted); margin-bottom: 1rem;">Ingresa los valores cuantificados para cada examen de la orden:</p>

        @php $index = 0; @endphp
        @foreach($orden->examenes as $examen)
            <fieldset class="card" style="padding: 1rem; margin-bottom: 1rem;">
                <legend style="font-weight: 700; color: var(--color-primary); padding: 0 0.5rem;">{{ $examen->nombre }} ({{ $examen->categoria }})</legend>
                
                <input type="hidden" name="detalles[{{ $index }}][examen_laboratorio_id]" value="{{ $examen->id }}">

                <div class="cycle-grid">
                    <label>Parámetro o Analito *
                        <input type="text" class="form-input" name="detalles[{{ $index }}][parametro]" value="{{ old("detalles.{$index}.parametro", $examen->nombre) }}" required>
                    </label>

                    <label>Valor Obtenido *
                        <input type="text" class="form-input" name="detalles[{{ $index }}][valor]" value="{{ old("detalles.{$index}.valor") }}" placeholder="Ej: 14.2, Positivo, Normal..." required>
                    </label>

                    <label>Unidad de Medida
                        <input type="text" class="form-input" name="detalles[{{ $index }}][unidad_medida]" value="{{ old("detalles.{$index}.unidad_medida", $examen->unidad_medida) }}" placeholder="Ej: g/dL, mg/dL, %">
                    </label>

                    <label>Rango de Referencia
                        <input type="text" class="form-input" name="detalles[{{ $index }}][rango_referencia]" value="{{ old("detalles.{$index}.rango_referencia", $examen->valores_referencia) }}" placeholder="Ej: 12.0 - 16.0">
                    </label>

                    <label>Interpretación
                        <select class="form-input" name="detalles[{{ $index }}][interpretacion]">
                            <option value="Normal">Normal</option>
                            <option value="Bajo">Bajo</option>
                            <option value="Alto">Alto</option>
                            <option value="Crítico">Crítico</option>
                        </select>
                    </label>
                </div>
            </fieldset>
            @php $index++; @endphp
        @endforeach

        <!-- Adjuntar PDF Oficial -->
        <div style="margin-top: 1.5rem;">
            <label>Adjuntar Informe PDF Oficial (Opcional, máx 10 MB)
                <input type="file" class="form-input" name="archivo_pdf" accept="application/pdf">
            </label>
            <small style="color: var(--color-text-muted);">El archivo se almacenará en almacenamiento privado seguro y solo podrá ser descargado por personal y pacientes autorizados.</small>
        </div>

        <div style="margin-top: 1.5rem;">
            <label>Observaciones del Bioquímico / Analista
                <textarea class="form-input" name="observaciones" rows="3" placeholder="Comentarios técnicos, metodología utilizada o notas de validación...">{{ old('observaciones') }}</textarea>
            </label>
        </div>

        <div class="cycle-actions" style="margin-top: 1.5rem;">
            <button class="btn btn-primary">Guardar en Borrador</button>
            <a class="btn btn-secondary" href="{{ route('laboratorio.ordenes.show', $orden) }}">Cancelar</a>
        </div>
    </form>
</div>
@endsection
