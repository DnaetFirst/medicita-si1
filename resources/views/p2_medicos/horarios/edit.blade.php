@extends('layouts.app')

@section('content')
<div style="max-width: 880px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: var(--font-2xl); font-weight: 700; color: var(--color-primary); margin-bottom: 0.25rem;">
                Configuración de Agenda Semanal (CU5)
            </h1>
            <p style="color: var(--color-text-muted); font-size: var(--font-sm);">
                Médico: <strong>{{ $medico->user?->name }}</strong> · Especialidad: <strong>{{ $medico->especialidad?->nombre }}</strong>
            </p>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <a href="{{ route('medicos.show', $medico) }}" class="btn btn-secondary">
                ← Volver a Ficha
            </a>
            <a href="{{ route('horarios.index') }}" class="btn btn-secondary">
                Listado General
            </a>
        </div>
    </div>

    <!-- Banner Informativo de Reglas de Negocio -->
    <div class="card" style="background: #EBF8FF; border-left: 4px solid #3182CE; padding: 1rem 1.25rem; margin-bottom: 1.5rem;">
        <div style="font-weight: 700; color: #2B6CB0; margin-bottom: 0.25rem; font-size: 0.9rem;">
            📌 Reglas Clínicas de Generación de Agenda (Ciclo 1):
        </div>
        <ul style="font-size: 0.8125rem; color: #2D3748; margin-left: 1.25rem; line-height: 1.5;">
            <li><strong>Duración del turno:</strong> Fija en <strong>15 minutos</strong> (no configurable).</li>
            <li><strong>Descanso obligatorio:</strong> Se bloquea automáticamente <strong>1 hora completa</strong> (4 slots de 15 min no reservables) a partir de la hora de almuerzo definida.</li>
            <li>Al guardar los cambios, el sistema generará y actualizará los slots para los próximos 7 días automáticamente.</li>
        </ul>
    </div>

    <div class="card">
        <form action="{{ route('horarios.update', $medico) }}" method="POST">
            @csrf
            @method('PUT')

            <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                @foreach($dias as $numDia => $nombreDia)
                    @php
                        $horario = $horariosExistentes[$numDia] ?? null;
                        $estaActivo = $horario ? $horario->activo : ($numDia <= 5);
                        $horaIni = $horario ? substr($horario->hora_inicio, 0, 5) : '08:00';
                        $horaFin = $horario ? substr($horario->hora_fin, 0, 5) : '17:00';
                        $descansoIni = $horario ? substr($horario->descanso_inicio, 0, 5) : '12:00';
                    @endphp

                    <div style="padding: 1rem; border: 1px solid var(--color-border); border-radius: var(--border-radius-sm); background: {{ $estaActivo ? '#FFFFFF' : '#F8FAFC' }};">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem;">
                            <label style="display: flex; align-items: center; gap: 0.625rem; cursor: pointer; font-weight: 700; color: var(--color-primary); font-size: 1rem;">
                                <input type="checkbox" name="dias[{{ $numDia }}][activo]" value="1" {{ $estaActivo ? 'checked' : '' }} onchange="toggleDiaRow(this, {{ $numDia }})" style="width: 18px; height: 18px; accent-color: var(--color-secondary);">
                                {{ $nombreDia }}
                            </label>

                            <span id="badge_{{ $numDia }}" class="badge {{ $estaActivo ? 'badge-success' : '' }}" style="{{ $estaActivo ? '' : 'background: #E2E8F0; color: #718096;' }}">
                                {{ $estaActivo ? 'Atención Habilitada' : 'No Laborable' }}
                            </span>
                        </div>

                        <div id="row_{{ $numDia }}" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; {{ $estaActivo ? '' : 'opacity: 0.4; pointer-events: none;' }}">
                            <div>
                                <label class="form-label" style="font-size: 0.75rem;">Hora de Entrada</label>
                                <input type="time" name="dias[{{ $numDia }}][hora_inicio]" class="form-input" value="{{ $horaIni }}">
                            </div>

                            <div>
                                <label class="form-label" style="font-size: 0.75rem;">Hora de Salida</label>
                                <input type="time" name="dias[{{ $numDia }}][hora_fin]" class="form-input" value="{{ $horaFin }}">
                            </div>

                            <div>
                                <label class="form-label" style="font-size: 0.75rem; color: #DD6B20;">
                                    ☕ Inicio Almuerzo (1h Fija)
                                </label>
                                <input type="time" name="dias[{{ $numDia }}][descanso_inicio]" class="form-input" value="{{ $descansoIni }}">
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; border-top: 1px solid var(--color-border); padding-top: 1.25rem; margin-top: 1.5rem;">
                <a href="{{ route('medicos.show', $medico) }}" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.5rem;">
                    Guardar Configuración y Generar Slots
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleDiaRow(chk, diaNum) {
    const row = document.getElementById('row_' + diaNum);
    const badge = document.getElementById('badge_' + diaNum);

    if (chk.checked) {
        row.style.opacity = '1';
        row.style.pointerEvents = 'auto';
        badge.className = 'badge badge-success';
        badge.style = '';
        badge.innerText = 'Atención Habilitada';
    } else {
        row.style.opacity = '0.4';
        row.style.pointerEvents = 'none';
        badge.className = 'badge';
        badge.style = 'background: #E2E8F0; color: #718096;';
        badge.innerText = 'No Laborable';
    }
}
</script>
@endsection
