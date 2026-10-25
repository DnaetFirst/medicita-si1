@extends('layouts.app')

@section('content')
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 style="font-size: var(--font-2xl); font-weight: 700; color: var(--color-primary); margin-bottom: 0.25rem;">
            Bloqueos de Agenda y Postergaciones (CU6)
        </h1>
        <p style="color: var(--color-text-muted); font-size: var(--font-sm);">
            Gestión de feriados, licencias médicas, capacitaciones y contingencias clínicas
        </p>
    </div>
    <button type="button" class="btn btn-primary" onclick="openBloqueoModal()" style="box-shadow: 0 4px 10px rgba(13, 124, 124, 0.2);">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        Registrar Nuevo Bloqueo
    </button>
</div>

<!-- Filtros -->
<div class="card" style="padding: 1rem; margin-bottom: 1.5rem;">
    <form method="GET" action="{{ route('bloqueos.index') }}" style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
        <div style="flex: 1; min-width: 200px;">
            <select name="medico_id" class="form-input" style="width: 100%;">
                <option value="">Todos los Médicos</option>
                @foreach($medicos as $m)
                    <option value="{{ $m->id }}" {{ ($filters['medico_id'] ?? '') == $m->id ? 'selected' : '' }}>
                        {{ $m->user?->name }} ({{ $m->especialidad?->nombre }})
                    </option>
                @endforeach
            </select>
        </div>

        <div style="flex: 1; min-width: 160px;">
            <select name="tipo" class="form-input" style="width: 100%;">
                <option value="">Todos los Tipos</option>
                <option value="feriado" {{ ($filters['tipo'] ?? '') === 'feriado' ? 'selected' : '' }}>Feriado</option>
                <option value="permiso" {{ ($filters['tipo'] ?? '') === 'permiso' ? 'selected' : '' }}>Permiso / Licencia</option>
                <option value="capacitacion" {{ ($filters['tipo'] ?? '') === 'capacitacion' ? 'selected' : '' }}>Capacitación</option>
                <option value="emergencia" {{ ($filters['tipo'] ?? '') === 'emergencia' ? 'selected' : '' }}>Emergencia</option>
            </select>
        </div>

        <button type="submit" class="btn btn-secondary">Filtrar</button>
        <a href="{{ route('bloqueos.index') }}" class="btn btn-secondary" style="color: var(--color-text-muted);">Limpiar</a>
    </form>
</div>

<!-- Tabla de Bloqueos -->
<div class="card" style="padding: 0; overflow: hidden;">
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.875rem;">
            <thead>
                <tr style="background: #F8FAFC; border-bottom: 1px solid var(--color-border); color: var(--color-text-muted); font-size: 0.75rem; text-transform: uppercase;">
                    <th style="padding: 0.875rem 1.25rem;">Médico Afectado</th>
                    <th style="padding: 0.875rem 1.25rem;">Período del Bloqueo</th>
                    <th style="padding: 0.875rem 1.25rem;">Tipo</th>
                    <th style="padding: 0.875rem 1.25rem;">Motivo / Justificación</th>
                    <th style="padding: 0.875rem 1.25rem;">Registrado Por</th>
                    <th style="padding: 0.875rem 1.25rem; text-align: right;">Acción</th>
                </tr>
            </thead>
            <tbody>
                @forelse($bloqueos as $b)
                    <tr style="border-bottom: 1px solid var(--color-border);">
                        <td style="padding: 1rem 1.25rem;">
                            <div style="font-weight: 600; color: var(--color-primary);">{{ $b->medico?->user?->name }}</div>
                            <span class="badge" style="background: {{ $b->medico?->especialidad?->color ?? '#0D7C7C' }}15; color: {{ $b->medico?->especialidad?->color ?? '#0D7C7C' }}; font-size: 0.7rem;">
                                {{ $b->medico?->especialidad?->nombre }}
                            </span>
                        </td>
                        <td style="padding: 1rem 1.25rem; font-family: monospace; font-size: 0.8125rem;">
                            {{ $b->fecha_inicio->format('d/m/Y H:i') }}<br>
                            al {{ $b->fecha_fin->format('d/m/Y H:i') }}
                        </td>
                        <td style="padding: 1rem 1.25rem;">
                            @php
                                $tipoColors = [
                                    'feriado' => 'badge-info',
                                    'permiso' => 'badge-warning',
                                    'capacitacion' => 'badge-info',
                                    'emergencia' => 'badge-danger',
                                ];
                            @endphp
                            <span class="badge {{ $tipoColors[$b->tipo] ?? 'badge-warning' }}">
                                {{ ucfirst($b->tipo) }}
                            </span>
                        </td>
                        <td style="padding: 1rem 1.25rem; color: var(--color-text);">
                            {{ $b->motivo }}
                        </td>
                        <td style="padding: 1rem 1.25rem; font-size: 0.8125rem; color: var(--color-text-muted);">
                            {{ $b->registradoPor?->name ?? 'Sistema' }}
                        </td>
                        <td style="padding: 1rem 1.25rem; text-align: right;">
                            <form action="{{ route('bloqueos.destroy', $b) }}" method="POST" onsubmit="return confirm('¿Estás seguro de levantar este bloqueo? Los slots volverán a estar disponibles.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-secondary" style="padding: 0.25rem 0.5rem; font-size: 0.75rem; color: var(--color-danger);">
                                    Levantar Bloqueo
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="padding: 2.5rem; text-align: center; color: var(--color-text-muted);">
                            No existen bloqueos de agenda registrados en este momento.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($bloqueos->hasPages())
        <div style="padding: 1rem; border-top: 1px solid var(--color-border);">
            {{ $bloqueos->links() }}
        </div>
    @endif
</div>

<!-- Modal Registrar Bloqueo -->
<div id="bloqueoModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 200; align-items: center; justify-content: center; padding: 1rem;">
    <div class="card" style="width: 100%; max-width: 520px; margin-bottom: 0; box-shadow: var(--shadow-xl);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; border-bottom: 1px solid var(--color-border); padding-bottom: 0.75rem;">
            <h2 style="font-size: var(--font-lg); font-weight: 700; color: var(--color-primary);">
                Registrar Bloqueo de Agenda
            </h2>
            <button type="button" onclick="closeBloqueoModal()" style="background: none; border: none; font-size: 1.25rem; cursor: pointer; color: var(--color-text-muted);">
                &times;
            </button>
        </div>

        <form action="{{ route('bloqueos.store') }}" method="POST">
            @csrf

            <div class="form-group">
                <label class="form-label" for="medico_id">Médico Afectado *</label>
                <select name="medico_id" id="medico_id" class="form-input" required>
                    <option value="">Selecciona el médico</option>
                    @foreach($medicos as $m)
                        <option value="{{ $m->id }}">{{ $m->user?->name }} ({{ $m->especialidad?->nombre }})</option>
                    @endforeach
                </select>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
                <div>
                    <label class="form-label" for="fecha_inicio">Fecha y Hora Inicio *</label>
                    <input type="datetime-local" name="fecha_inicio" id="fecha_inicio" class="form-input" required>
                </div>
                <div>
                    <label class="form-label" for="fecha_fin">Fecha y Hora Fin *</label>
                    <input type="datetime-local" name="fecha_fin" id="fecha_fin" class="form-input" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="tipo">Tipo de Bloqueo *</label>
                <select name="tipo" id="tipo" class="form-input" required>
                    <option value="permiso">Permiso / Licencia Personal</option>
                    <option value="feriado">Feriado o Asueto Institucional</option>
                    <option value="capacitacion">Capacitación / Congreso Médico</option>
                    <option value="emergencia">Emergencia / Contingencia</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="motivo">Motivo o Justificación *</label>
                <textarea name="motivo" id="motivo" class="form-input" rows="2" placeholder="Describe brevemente la razón del bloqueo..." required></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; border-top: 1px solid var(--color-border); padding-top: 1rem;">
                <button type="button" class="btn btn-secondary" onclick="closeBloqueoModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Registrar Bloqueo</button>
            </div>
        </form>
    </div>
</div>

<script>
function openBloqueoModal() {
    document.getElementById('bloqueoModal').style.display = 'flex';
}
function closeBloqueoModal() {
    document.getElementById('bloqueoModal').style.display = 'none';
}
</script>
@endsection
