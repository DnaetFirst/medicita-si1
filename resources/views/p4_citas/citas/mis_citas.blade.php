@extends('layouts.app')

@section('content')
<div style="max-width: 1100px; margin: 0 auto;">
    <!-- Encabezado de Página -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: var(--font-2xl); font-weight: 700; color: var(--color-primary); margin: 0 0 0.25rem 0;">
                Mis Citas Médicas
            </h1>
            <p style="color: var(--color-text-muted); font-size: var(--font-sm); margin: 0;">
                Historial completo y gestión de tus fichas médicas en MediCita Pro
            </p>
        </div>
        <a href="{{ route('citas.nueva') }}" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 2px 6px rgba(13,124,124,0.25);">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
            Solicitar Nueva Ficha (CU8)
        </a>
    </div>

    <!-- Tarjeta de Identificación del Paciente -->
    <div class="card" style="margin-bottom: 1.5rem; background: linear-gradient(135deg, rgba(13,124,124,0.06), rgba(42,157,143,0.04)); border: 1px solid rgba(13,124,124,0.15);">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <div style="width: 46px; height: 46px; border-radius: 50%; background: var(--color-primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1.1rem;">
                    {{ strtoupper(substr($paciente->nombres, 0, 1) . substr($paciente->apellidos, 0, 1)) }}
                </div>
                <div>
                    <div style="font-size: 1.1rem; font-weight: 700; color: var(--color-primary);">
                        {{ $paciente->nombre_completo }}
                    </div>
                    <div style="font-size: 0.85rem; color: var(--color-text-muted);">
                        Cédula: <strong>{{ $paciente->ci }}</strong> &bull; Teléfono: <strong>{{ $paciente->telefono }}</strong> &bull; Grupo Sanguíneo: <strong>{{ $paciente->tipo_sangre ?? 'No reg.' }}</strong>
                    </div>
                </div>
            </div>
            <div>
                <span class="badge badge-success" style="font-size: 0.8rem; padding: 0.35rem 0.75rem;">
                    Paciente Registrado &bull; CU7
                </span>
            </div>
        </div>
    </div>

    <!-- Filtros de Estado -->
    <div class="card" style="padding: 1rem 1.25rem; margin-bottom: 1.5rem;">
        <form method="GET" action="{{ route('citas.mis_citas') }}" style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
            <label style="font-size: 0.85rem; font-weight: 600; color: var(--color-text);">Filtrar por estado:</label>
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                <a href="{{ route('citas.mis_citas') }}" class="btn btn-sm {{ empty($filters['estado']) ? 'btn-primary' : 'btn-secondary' }}">
                    Todas
                </a>
                <a href="{{ route('citas.mis_citas', ['estado' => 'programada']) }}" class="btn btn-sm {{ ($filters['estado'] ?? '') === 'programada' ? 'btn-primary' : 'btn-secondary' }}">
                    Programadas
                </a>
                <a href="{{ route('citas.mis_citas', ['estado' => 'atendida']) }}" class="btn btn-sm {{ ($filters['estado'] ?? '') === 'atendida' ? 'btn-primary' : 'btn-secondary' }}">
                    Atendidas
                </a>
                <a href="{{ route('citas.mis_citas', ['estado' => 'cancelada']) }}" class="btn btn-sm {{ ($filters['estado'] ?? '') === 'cancelada' ? 'btn-primary' : 'btn-secondary' }}">
                    Canceladas
                </a>
                <a href="{{ route('citas.mis_citas', ['estado' => 'no_asistio']) }}" class="btn btn-sm {{ ($filters['estado'] ?? '') === 'no_asistio' ? 'btn-primary' : 'btn-secondary' }}">
                    No Asistió
                </a>
            </div>
        </form>
    </div>

    <!-- Listado de Citas -->
    <div class="card" style="padding: 0; overflow: hidden; margin-bottom: 1.5rem;">
        <div style="overflow-x: auto;">
            <table class="table" style="width: 100%; border-collapse: collapse; margin: 0;">
                <thead>
                    <tr style="background: var(--color-surface-hover, #f8fafc); border-bottom: 1px solid var(--color-border); text-align: left; font-size: 0.8rem; text-transform: uppercase; color: var(--color-text-muted); letter-spacing: 0.5px;">
                        <th style="padding: 0.85rem 1.25rem;">Ficha</th>
                        <th style="padding: 0.85rem 1rem;">Especialidad & Médico</th>
                        <th style="padding: 0.85rem 1rem;">Fecha & Turno</th>
                        <th style="padding: 0.85rem 1rem;">Estado</th>
                        <th style="padding: 0.85rem 1.25rem; text-align: right;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($citas as $cita)
                        @php
                            $badgeClass = match($cita->estado) {
                                'programada' => 'badge-info',
                                'confirmada' => 'badge-primary',
                                'en_curso'   => 'badge-warning',
                                'atendida'   => 'badge-success',
                                'cancelada'  => 'badge-danger',
                                'no_asistio' => 'badge-danger',
                                default      => 'badge-secondary',
                            };
                            $estadoLabel = match($cita->estado) {
                                'programada' => 'Programada',
                                'confirmada' => 'Confirmada',
                                'en_curso'   => 'En Consulta',
                                'atendida'   => 'Atendida',
                                'cancelada'  => 'Cancelada',
                                'no_asistio' => 'No Asistió',
                                default      => ucfirst($cita->estado),
                            };
                        @endphp
                        <tr style="border-bottom: 1px solid var(--color-border);">
                            <td style="padding: 1rem 1.25rem;">
                                <div style="font-family: monospace; font-weight: 700; color: var(--color-primary); font-size: 0.95rem;">
                                    {{ $cita->codigo_ficha }}
                                </div>
                                <div style="font-size: 0.75rem; color: var(--color-text-muted); margin-top: 0.2rem;">
                                    {{ $cita->fecha_registro ? $cita->fecha_registro->format('d/m/Y H:i') : '' }}
                                </div>
                            </td>
                            <td style="padding: 1rem 1rem;">
                                <div style="font-weight: 600; color: var(--color-text);">
                                    {{ $cita->medico?->especialidad?->nombre ?? 'Especialidad general' }}
                                </div>
                                <div style="font-size: 0.82rem; color: var(--color-text-muted);">
                                    Dr. {{ $cita->medico?->user?->name ?? 'Médico Asignado' }}
                                </div>
                            </td>
                            <td style="padding: 1rem 1rem;">
                                <div style="font-weight: 600; color: var(--color-text);">
                                    {{ $cita->fecha ? $cita->fecha->format('d/m/Y') : '' }}
                                </div>
                                <div style="font-size: 0.85rem; color: var(--color-primary); font-weight: 700; font-family: monospace;">
                                    {{ substr($cita->hora, 0, 5) }} hrs
                                </div>
                            </td>
                            <td style="padding: 1rem 1rem;">
                                <span class="badge {{ $badgeClass }}" style="font-weight: 600; font-size: 0.75rem;">
                                    {{ $estadoLabel }}
                                </span>
                            </td>
                            <td style="padding: 1rem 1.25rem; text-align: right;">
                                <div style="display: inline-flex; gap: 0.4rem; align-items: center;">
                                    <a href="{{ route('citas.show', $cita) }}" class="btn btn-sm btn-secondary" title="Ver Comprobante">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                        Comprobante
                                    </a>

                                    @if(in_array($cita->estado, ['programada', 'confirmada']))
                                        <a href="{{ route('citas.reprogramar', $cita) }}" class="btn btn-sm btn-secondary" style="color: var(--color-primary); font-weight: 600;" title="Reprogramar Turno (CU10)">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 21h5v-5"/></svg>
                                            Reprogramar
                                        </a>

                                        <button type="button" class="btn btn-sm btn-danger" onclick="abrirModalCancelar({{ $cita->id }}, '{{ $cita->codigo_ficha }}')" title="Cancelar Ficha (CU9)">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                            Cancelar
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 3rem 1rem; color: var(--color-text-muted);">
                                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom: 0.5rem; opacity: 0.5;"><circle cx="12" cy="12" r="10"/><path d="M8 12h8"/></svg>
                                <div style="font-weight: 600; font-size: 1rem; color: var(--color-text); margin-bottom: 0.25rem;">No tienes citas registradas en este estado</div>
                                <div style="font-size: 0.85rem; margin-bottom: 1rem;">Puedes agendar una nueva consulta con nuestros médicos especialistas.</div>
                                <a href="{{ route('citas.nueva') }}" class="btn btn-primary btn-sm">
                                    + Solicitar Ficha Médica
                                </a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($citas->hasPages())
            <div style="padding: 1rem 1.25rem; border-top: 1px solid var(--color-border);">
                {{ $citas->links() }}
            </div>
        @endif
    </div>
</div>

<!-- Modal Cancelar Cita (CU9) -->
<div id="modalCancelar" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 1rem;">
    <div class="card" style="max-width: 500px; width: 100%; margin: 0; background: white; padding: 1.5rem; border-radius: var(--radius-md);">
        <h3 style="margin-top: 0; color: var(--color-danger); font-size: 1.15rem;">Cancelar Cita Médica</h3>
        <p style="font-size: 0.85rem; color: var(--color-text-muted);">
            ¿Estás seguro de cancelar la cita <strong id="lblCodigoCancelar"></strong>? El cupo horario asignado será liberado en el sistema.
        </p>
        <form id="formCancelarCita" method="POST" action="">
            @csrf
            <div style="margin-bottom: 1rem;">
                <label class="form-label" style="font-weight: 600;">Motivo de la cancelación *</label>
                <textarea name="motivo_cancelacion" class="form-input" rows="3" required placeholder="Por favor detalla el motivo de la cancelación (mínimo 5 caracteres)..."></textarea>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" class="btn btn-secondary" onclick="cerrarModalCancelar()">Volver</button>
                <button type="submit" class="btn btn-danger">Confirmar Cancelación</button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirModalCancelar(citaId, codigo) {
    document.getElementById('lblCodigoCancelar').textContent = codigo;
    document.getElementById('formCancelarCita').action = `/citas/${citaId}/cancelar`;
    document.getElementById('modalCancelar').style.display = 'flex';
}
function cerrarModalCancelar() {
    document.getElementById('modalCancelar').style.display = 'none';
}
</script>
@endsection
