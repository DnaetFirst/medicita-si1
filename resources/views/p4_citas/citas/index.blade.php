@extends('layouts.app')

@section('content')
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 style="font-size: var(--font-2xl); font-weight: 700; color: var(--color-primary); margin-bottom: 0.25rem;">
            Control de Citas Médicas y Recepción (CU10)
        </h1>
        <p style="color: var(--color-text-muted); font-size: var(--font-sm);">
            Gestión de turnos, check-in en sala de espera, transiciones de atención médica y cancelaciones
        </p>
    </div>
    <a href="{{ route('citas.nueva') }}" class="btn btn-primary" style="box-shadow: 0 4px 10px rgba(13, 124, 124, 0.2);">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v8"/><path d="M8 12h8"/></svg>
        Solicitar Ficha / Nueva Cita (CU8)
    </a>
</div>

<!-- KPIs del Día -->
<div class="grid-4" style="margin-bottom: 1.5rem;">
    <div class="card" style="margin-bottom: 0; padding: 1rem;">
        <span style="font-size: 0.75rem; color: var(--color-text-muted); font-weight: 600; text-transform: uppercase;">Total Citas Hoy</span>
        <div style="font-size: 1.75rem; font-weight: 700; color: var(--color-primary);">{{ $kpis['total_hoy'] }}</div>
    </div>
    <div class="card" style="margin-bottom: 0; padding: 1rem;">
        <span style="font-size: 0.75rem; color: var(--color-text-muted); font-weight: 600; text-transform: uppercase;">Por Llegar (Programadas)</span>
        <div style="font-size: 1.75rem; font-weight: 700; color: var(--color-info);">{{ $kpis['programadas'] }}</div>
    </div>
    <div class="card" style="margin-bottom: 0; padding: 1rem;">
        <span style="font-size: 0.75rem; color: var(--color-text-muted); font-weight: 600; text-transform: uppercase;">En Espera (Check-in)</span>
        <div style="font-size: 1.75rem; font-weight: 700; color: var(--color-secondary);">{{ $kpis['confirmadas_checkin'] }}</div>
    </div>
    <div class="card" style="margin-bottom: 0; padding: 1rem;">
        <span style="font-size: 0.75rem; color: var(--color-text-muted); font-weight: 600; text-transform: uppercase;">Atendidas con Éxito</span>
        <div style="font-size: 1.75rem; font-weight: 700; color: var(--color-success);">{{ $kpis['atendidas'] }}</div>
    </div>
</div>

<!-- Filtros -->
<div class="card" style="padding: 1rem; margin-bottom: 1.5rem;">
    <form method="GET" action="{{ route('citas.index') }}" style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
        <div style="flex: 2; min-width: 220px;">
            <input type="text" name="search" class="form-input" placeholder="Buscar por código de ficha, paciente o CI..." value="{{ $filters['search'] ?? '' }}" style="width: 100%;">
        </div>

        <div style="flex: 1; min-width: 150px;">
            <input type="date" name="fecha" class="form-input" value="{{ $filters['fecha'] ?? '' }}" style="width: 100%;">
        </div>

        <div style="flex: 1; min-width: 160px;">
            <select name="especialidad_id" class="form-input" style="width: 100%;">
                <option value="">Todas las Especialidades</option>
                @foreach($especialidades as $esp)
                    <option value="{{ $esp->id }}" {{ ($filters['especialidad_id'] ?? '') == $esp->id ? 'selected' : '' }}>
                        {{ $esp->nombre }}
                    </option>
                @endforeach
            </select>
        </div>

        <div style="flex: 1; min-width: 160px;">
            <select name="medico_id" class="form-input" style="width: 100%;">
                <option value="">Todos los Médicos</option>
                @foreach($medicos as $med)
                    <option value="{{ $med->id }}" {{ ($filters['medico_id'] ?? '') == $med->id ? 'selected' : '' }}>
                        {{ $med->user?->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div style="flex: 1; min-width: 140px;">
            <select name="estado" class="form-input" style="width: 100%;">
                <option value="">Todos los Estados</option>
                <option value="programada" {{ ($filters['estado'] ?? '') === 'programada' ? 'selected' : '' }}>Programada</option>
                <option value="confirmada" {{ ($filters['estado'] ?? '') === 'confirmada' ? 'selected' : '' }}>En Espera (Check-in)</option>
                <option value="en_curso" {{ ($filters['estado'] ?? '') === 'en_curso' ? 'selected' : '' }}>En Consulta</option>
                <option value="atendida" {{ ($filters['estado'] ?? '') === 'atendida' ? 'selected' : '' }}>Atendida</option>
                <option value="cancelada" {{ ($filters['estado'] ?? '') === 'cancelada' ? 'selected' : '' }}>Cancelada</option>
                <option value="no_asistio" {{ ($filters['estado'] ?? '') === 'no_asistio' ? 'selected' : '' }}>No Asistió</option>
            </select>
        </div>

        <button type="submit" class="btn btn-secondary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            Filtrar
        </button>

        @if(!empty($filters['search']) || !empty($filters['fecha']) || !empty($filters['estado']) || !empty($filters['medico_id']) || !empty($filters['especialidad_id']))
            <a href="{{ route('citas.index') }}" class="btn btn-secondary" style="color: var(--color-text-muted);">
                Limpiar
            </a>
        @endif
    </form>
</div>

<!-- Tabla de Citas -->
<div class="card" style="padding: 0; overflow: hidden;">
    <div style="overflow-x: auto;">
        <table class="table" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: var(--color-surface-hover); text-align: left; border-bottom: 1px solid var(--color-border);">
                    <th style="padding: 0.85rem 1rem; font-size: 0.8rem; font-weight: 600; color: var(--color-text-muted);">CÓDIGO FICHA</th>
                    <th style="padding: 0.85rem 1rem; font-size: 0.8rem; font-weight: 600; color: var(--color-text-muted);">PACIENTE</th>
                    <th style="padding: 0.85rem 1rem; font-size: 0.8rem; font-weight: 600; color: var(--color-text-muted);">MÉDICO / ESPECIALIDAD</th>
                    <th style="padding: 0.85rem 1rem; font-size: 0.8rem; font-weight: 600; color: var(--color-text-muted);">FECHA & TURNO (15m)</th>
                    <th style="padding: 0.85rem 1rem; font-size: 0.8rem; font-weight: 600; color: var(--color-text-muted);">ESTADO</th>
                    <th style="padding: 0.85rem 1rem; font-size: 0.8rem; font-weight: 600; color: var(--color-text-muted); text-align: right;">CONTROL DE ASISTENCIA</th>
                </tr>
            </thead>
            <tbody>
                @forelse($citas as $cita)
                    <tr style="border-bottom: 1px solid var(--color-border); transition: background 0.15s ease;">
                        <td style="padding: 0.85rem 1rem;">
                            <a href="{{ route('citas.show', $cita) }}" style="font-family: monospace; font-weight: 700; color: var(--color-primary); text-decoration: none; font-size: 0.95rem;">
                                {{ $cita->codigo_ficha }}
                            </a>
                            @if($cita->reprogramada_de_id)
                                <div style="font-size: 0.7rem; color: var(--color-secondary); font-weight: 600;">(Reprogramada)</div>
                            @endif
                        </td>
                        <td style="padding: 0.85rem 1rem;">
                            <div style="font-weight: 600;">{{ $cita->paciente?->nombre_completo }}</div>
                            <div style="font-size: 0.75rem; color: var(--color-text-muted);">CI: {{ $cita->paciente?->ci }} &bull; Cel: {{ $cita->paciente?->telefono }}</div>
                        </td>
                        <td style="padding: 0.85rem 1rem;">
                            <div style="font-weight: 500;">{{ $cita->medico?->user?->name }}</div>
                            <div style="font-size: 0.75rem; color: var(--color-text-muted);">{{ $cita->medico?->especialidad?->nombre }}</div>
                        </td>
                        <td style="padding: 0.85rem 1rem;">
                            <div style="font-weight: 600; color: var(--color-primary);">
                                {{ $cita->fecha ? $cita->fecha->format('d/m/Y') : '' }}
                            </div>
                            <div style="font-size: 0.8rem; font-weight: 700; color: var(--color-secondary);">
                                {{ substr($cita->hora, 0, 5) }} hrs
                            </div>
                        </td>
                        <td style="padding: 0.85rem 1rem;">
                            @php
                                $badgeClass = match($cita->estado) {
                                    'programada' => 'badge-info',
                                    'confirmada' => 'badge-primary',
                                    'en_curso' => 'badge-warning',
                                    'atendida' => 'badge-success',
                                    'cancelada' => 'badge-danger',
                                    'no_asistio' => 'badge-danger',
                                    default => 'badge-secondary',
                                };
                                $estadoLabel = match($cita->estado) {
                                    'programada' => 'Programada',
                                    'confirmada' => 'En Espera (Check-in)',
                                    'en_curso' => 'En Consulta',
                                    'atendida' => 'Atendida',
                                    'cancelada' => 'Cancelada',
                                    'no_asistio' => 'No Asistió',
                                    default => ucfirst($cita->estado),
                                };
                            @endphp
                            <span class="badge {{ $badgeClass }}">
                                {{ $estadoLabel }}
                            </span>
                            @if($cita->hora_checkin)
                                <div style="font-size: 0.7rem; color: var(--color-text-muted); margin-top: 0.2rem;">
                                    Llegada: {{ $cita->hora_checkin->format('H:i') }}
                                </div>
                            @endif
                        </td>
                        <td style="padding: 0.85rem 1rem; text-align: right;">
                            <div style="display: flex; justify-content: flex-end; gap: 0.4rem; flex-wrap: wrap;">
                                <!-- Botón Comprobante / Ficha -->
                                <a href="{{ route('citas.show', $cita) }}" class="btn btn-sm btn-secondary" title="Ver Comprobante">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                </a>

                                <!-- Check-in Recepción (CU10) -->
                                @if($cita->estado === 'programada')
                                    <form method="POST" action="{{ route('citas.inasistencia', $cita) }}" style="display: inline;">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-secondary">No asistio</button>
                                    </form>
                                    <form method="POST" action="{{ route('citas.checkin', $cita) }}" style="display: inline;">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-primary" title="Registrar llegada del paciente a recepción">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                                            Check-in
                                        </button>
                                    </form>
                                @endif

                                <!-- Iniciar Consulta Médico (CU10) -->
                                @if($cita->estado === 'confirmada')
                                    <form method="POST" action="{{ route('citas.iniciar', $cita) }}" style="display: inline;">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-secondary" style="color: #d97706; font-weight: 600;" title="Llamar a consultorio médico">
                                            Iniciar Consulta
                                        </button>
                                    </form>
                                @endif

                                <!-- Finalizar Consulta (CU10) -->
                                @if($cita->estado === 'en_curso')
                                    <button type="button" class="btn btn-sm btn-success" onclick="abrirModalFinalizar({{ $cita->id }}, '{{ $cita->codigo_ficha }}')">
                                        Concluir Atención
                                    </button>
                                @endif

                                <!-- Reprogramar o Cancelar (CU11) -->
                                @if(in_array($cita->estado, ['programada', 'confirmada']))
                                    <a href="{{ route('citas.reprogramar', $cita) }}" class="btn btn-sm btn-secondary" title="Reprogramar Turno (CU11)">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 21h5v-5"/></svg>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-danger" title="Cancelar Cita" onclick="abrirModalCancelar({{ $cita->id }}, '{{ $cita->codigo_ficha }}')">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="padding: 3rem; text-align: center; color: var(--color-text-muted);">
                            No hay citas médicas registradas que coincidan con los criterios seleccionados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($citas->hasPages())
        <div style="padding: 1rem; border-top: 1px solid var(--color-border);">
            {{ $citas->links() }}
        </div>
    @endif
</div>

<!-- Modal para Cancelar Cita con Motivo Obligatorio (CU11) -->
<div id="modalCancelar" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 1rem;">
    <div class="card" style="width: 100%; max-width: 480px; padding: 1.5rem; margin: 0;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--color-danger); margin: 0;">
                Cancelar Cita Médica (CU11)
            </h3>
            <button type="button" onclick="cerrarModalCancelar()" style="background: none; border: none; font-size: 1.25rem; cursor: pointer; color: var(--color-text-muted);">&times;</button>
        </div>

        <p style="font-size: 0.875rem; color: var(--color-text-muted); margin-bottom: 1rem;">
            Al cancelar la cita <strong id="lblCodigoCancelar"></strong>, el turno de 15 minutos quedará inmediatamente liberado para otros pacientes.
        </p>

        <form id="formCancelarCita" method="POST" action="">
            @csrf
            <div style="margin-bottom: 1.25rem;">
                <label class="form-label">Motivo de Cancelación (Obligatorio) *</label>
                <textarea name="motivo_cancelacion" class="form-input" required rows="3" placeholder="Ej. Solicitud del paciente por viaje imprevisto, reprogramación médica, etc."></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" class="btn btn-secondary" onclick="cerrarModalCancelar()">
                    Volver
                </button>
                <button type="submit" class="btn btn-danger">
                    Confirmar Cancelación y Liberar Turno
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal para Concluir Atención Médica (CU10) -->
<div id="modalFinalizar" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 1rem;">
    <div class="card" style="width: 100%; max-width: 520px; padding: 1.5rem; margin: 0;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--color-success); margin: 0;">
                Concluir Atención Médica (CU10)
            </h3>
            <button type="button" onclick="cerrarModalFinalizar()" style="background: none; border: none; font-size: 1.25rem; cursor: pointer; color: var(--color-text-muted);">&times;</button>
        </div>

        <form id="formFinalizarCita" method="POST" action="">
            @csrf
            <div style="margin-bottom: 1.25rem;">
                <label class="form-label">Diagnóstico / Observaciones de la Consulta</label>
                <textarea name="observaciones_atencion" class="form-input" rows="4" placeholder="Ej. Paciente acude por cuadro gripal de 3 días de evolución. Se prescribe tratamiento sintomático y reposo..."></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" class="btn btn-secondary" onclick="cerrarModalFinalizar()">
                    Cancelar
                </button>
                <button type="submit" class="btn btn-success">
                    Guardar Ficha y Finalizar Cita
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirModalCancelar(id, codigo) {
    document.getElementById('lblCodigoCancelar').innerText = codigo;
    document.getElementById('formCancelarCita').action = `/citas/${id}/cancelar`;
    document.getElementById('modalCancelar').style.display = 'flex';
}

function cerrarModalCancelar() {
    document.getElementById('modalCancelar').style.display = 'none';
}

function abrirModalFinalizar(id, codigo) {
    document.getElementById('formFinalizarCita').action = `/citas/${id}/finalizar`;
    document.getElementById('modalFinalizar').style.display = 'flex';
}

function cerrarModalFinalizar() {
    document.getElementById('modalFinalizar').style.display = 'none';
}
</script>
@endsection
