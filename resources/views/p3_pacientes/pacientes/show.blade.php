@extends('layouts.app')

@section('content')
<div style="margin-bottom: 1.5rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <h1 style="font-size: var(--font-2xl); font-weight: 700; color: var(--color-primary); margin: 0;">
                    {{ $paciente->nombre_completo }}
                </h1>
                <span class="badge {{ $paciente->activo ? 'badge-success' : 'badge-danger' }}">
                    {{ $paciente->activo ? 'Activo' : 'Inactivo' }}
                </span>
            </div>
            <p style="color: var(--color-text-muted); font-size: var(--font-sm); margin-top: 0.25rem;">
                Cédula de Identidad: <strong>{{ $paciente->ci }}</strong> &bull; Registrado el {{ $paciente->created_at->format('d/m/Y') }}
            </p>
        </div>
        <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
            <a href="{{ route('citas.nueva', ['paciente_id' => $paciente->id]) }}" class="btn btn-primary" style="box-shadow: 0 4px 10px rgba(13, 124, 124, 0.2);">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v8"/><path d="M8 12h8"/></svg>
                Solicitar Ficha / Agendar Cita (CU8)
            </a>
            <a href="{{ route('pacientes.edit', $paciente) }}" class="btn btn-secondary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>
                Editar
            </a>
            <a href="{{ route('pacientes.index') }}" class="btn btn-secondary">
                Volver
            </a>
        </div>
    </div>
</div>

<!-- Tarjetas de Información Personal y Clínica -->
<div class="grid-3" style="margin-bottom: 1.5rem;">
    <!-- Datos Clínicos y Biológicos -->
    <div class="card" style="margin-bottom: 0;">
        <h3 style="font-size: 1rem; font-weight: 700; color: var(--color-primary); margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
            Perfil Clínico
        </h3>
        <div style="font-size: 0.875rem;">
            <div style="display: flex; justify-content: space-between; padding: 0.4rem 0; border-bottom: 1px solid var(--color-border);">
                <span style="color: var(--color-text-muted);">Edad:</span>
                <strong>{{ $paciente->fecha_nacimiento ? $paciente->fecha_nacimiento->age . ' años' : 'N/D' }}</strong>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 0.4rem 0; border-bottom: 1px solid var(--color-border);">
                <span style="color: var(--color-text-muted);">Fecha de Nacimiento:</span>
                <span>{{ $paciente->fecha_nacimiento ? $paciente->fecha_nacimiento->format('d/m/Y') : 'N/D' }}</span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 0.4rem 0; border-bottom: 1px solid var(--color-border);">
                <span style="color: var(--color-text-muted);">Género:</span>
                <span>{{ $paciente->genero ?? 'No especificado' }}</span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 0.4rem 0;">
                <span style="color: var(--color-text-muted);">Grupo Sanguíneo:</span>
                <span class="badge badge-info" style="font-weight: 700;">{{ $paciente->grupo_sanguineo ?? 'Sin registrar' }}</span>
            </div>
        </div>
    </div>

    <!-- Contacto Directo -->
    <div class="card" style="margin-bottom: 0;">
        <h3 style="font-size: 1rem; font-weight: 700; color: var(--color-primary); margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
            Contacto del Paciente
        </h3>
        <div style="font-size: 0.875rem;">
            <div style="display: flex; justify-content: space-between; padding: 0.4rem 0; border-bottom: 1px solid var(--color-border);">
                <span style="color: var(--color-text-muted);">Teléfono / Móvil:</span>
                <strong>{{ $paciente->telefono }}</strong>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 0.4rem 0; border-bottom: 1px solid var(--color-border);">
                <span style="color: var(--color-text-muted);">Correo Electrónico:</span>
                <span>{{ $paciente->email ?? 'No registrado' }}</span>
            </div>
            <div style="padding: 0.4rem 0;">
                <span style="color: var(--color-text-muted); display: block; margin-bottom: 0.2rem;">Dirección:</span>
                <span>{{ $paciente->direccion ?? 'Sin dirección registrada' }}</span>
            </div>
        </div>
    </div>

    <!-- Contacto de Emergencia -->
    <div class="card" style="margin-bottom: 0;">
        <h3 style="font-size: 1rem; font-weight: 700; color: var(--color-danger); margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg>
            Contacto de Emergencia
        </h3>
        <div style="font-size: 0.875rem;">
            @if($paciente->contacto_emergencia_nombre)
                <div style="display: flex; justify-content: space-between; padding: 0.4rem 0; border-bottom: 1px solid var(--color-border);">
                    <span style="color: var(--color-text-muted);">Persona:</span>
                    <strong>{{ $paciente->contacto_emergencia_nombre }}</strong>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 0.4rem 0;">
                    <span style="color: var(--color-text-muted);">Teléfono Urgencias:</span>
                    <strong style="color: var(--color-danger);">{{ $paciente->contacto_emergencia_telefono ?? 'N/D' }}</strong>
                </div>
            @else
                <p style="color: var(--color-text-muted); margin: 0.5rem 0;">
                    No se especificó contacto de emergencia al momento del registro.
                </p>
            @endif
        </div>
    </div>
</div>

<!-- Sección CU7: Tutores y Responsables Autorizados -->
<div class="card" style="margin-bottom: 1.5rem; padding: 1.5rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.75rem;">
        <div>
            <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--color-primary); margin: 0;">
                Responsables Autorizados y Tutores Legales (CU7)
            </h3>
            <p style="color: var(--color-text-muted); font-size: var(--font-sm); margin: 0.25rem 0 0 0;">
                Representantes acreditados con control estricto de fecha límite de vigencia
            </p>
        </div>
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('modalResponsable').style.display = 'flex'">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="8" x2="8" y1="12" y2="12"/></svg>
            Añadir Responsable
        </button>
    </div>

    @if($paciente->responsables->isEmpty())
        <div style="text-align: center; padding: 2rem; background: var(--color-surface-hover); border-radius: var(--radius-md); border: 1px dashed var(--color-border);">
            <p style="color: var(--color-text-muted); margin: 0;">
                Este paciente no tiene tutores legales ni representantes autorizados registrados. Es titular directo.
            </p>
        </div>
    @else
        <div style="overflow-x: auto;">
            <table class="table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: var(--color-surface-hover); text-align: left; border-bottom: 1px solid var(--color-border);">
                        <th style="padding: 0.65rem 0.85rem; font-size: 0.75rem; color: var(--color-text-muted);">NOMBRE DEL RESPONSABLE</th>
                        <th style="padding: 0.65rem 0.85rem; font-size: 0.75rem; color: var(--color-text-muted);">CÉDULA (CI)</th>
                        <th style="padding: 0.65rem 0.85rem; font-size: 0.75rem; color: var(--color-text-muted);">PARENTESCO</th>
                        <th style="padding: 0.65rem 0.85rem; font-size: 0.75rem; color: var(--color-text-muted);">TELÉFONO</th>
                        <th style="padding: 0.65rem 0.85rem; font-size: 0.75rem; color: var(--color-text-muted);">VIGENCIA AUTORIZACIÓN</th>
                        <th style="padding: 0.65rem 0.85rem; font-size: 0.75rem; color: var(--color-text-muted);">ESTADO</th>
                        <th style="padding: 0.65rem 0.85rem; font-size: 0.75rem; color: var(--color-text-muted); text-align: right;">ACCIÓN</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($paciente->responsables as $resp)
                        @php
                            $vigente = $resp->isVigente();
                        @endphp
                        <tr style="border-bottom: 1px solid var(--color-border);">
                            <td style="padding: 0.75rem 0.85rem; font-weight: 600;">
                                {{ $resp->nombres }}
                            </td>
                            <td style="padding: 0.75rem 0.85rem; font-family: monospace;">
                                {{ $resp->ci }}
                            </td>
                            <td style="padding: 0.75rem 0.85rem;">
                                <span class="badge badge-info">{{ $resp->parentesco }}</span>
                            </td>
                            <td style="padding: 0.75rem 0.85rem;">
                                {{ $resp->telefono }}
                            </td>
                            <td style="padding: 0.75rem 0.85rem;">
                                <span style="font-weight: 500; {{ !$vigente ? 'color: var(--color-danger); font-weight: 700;' : '' }}">
                                    {{ $resp->fecha_limite_vigencia ? $resp->fecha_limite_vigencia->format('d/m/Y') : 'Indefinida' }}
                                </span>
                            </td>
                            <td style="padding: 0.75rem 0.85rem;">
                                @if(!$resp->activo)
                                    <span class="badge badge-danger">Revocada</span>
                                @elseif($vigente)
                                    <span class="badge badge-success">Vigente</span>
                                @else
                                    <span class="badge badge-warning">Vencida</span>
                                @endif
                            </td>
                            <td style="padding: 0.75rem 0.85rem; text-align: right;">
                                @if($resp->activo)
                                    <form method="POST" action="{{ route('pacientes.responsables.destroy', [$paciente, $resp]) }}" style="display: inline;" onsubmit="return confirm('¿Revocar la autorización de este responsable?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger" title="Revocar autorización">
                                            Revocar
                                        </button>
                                    </form>
                                @else
                                    <span style="font-size: 0.75rem; color: var(--color-text-muted);">Inactivo</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<!-- Sección Historial de Citas Médicas del Paciente -->
<div class="card" style="padding: 1.5rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <div>
            <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--color-primary); margin: 0;">
                Historial de Citas Médicas y Fichas
            </h3>
            <p style="color: var(--color-text-muted); font-size: var(--font-sm); margin: 0.25rem 0 0 0;">
                Consultas médicas agendadas, atendidas o reprogramadas
            </p>
        </div>
    </div>

    @if($paciente->citas->isEmpty())
        <div style="text-align: center; padding: 2.5rem; background: var(--color-surface-hover); border-radius: var(--radius-md);">
            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom: 0.5rem; color: var(--color-text-muted);"><rect width="18" height="18" x="3" y="4" rx="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
            <p style="color: var(--color-text-muted); margin-bottom: 0.75rem;">
                Este paciente no registra citas médicas previas en el sistema.
            </p>
            <a href="{{ route('citas.nueva', ['paciente_id' => $paciente->id]) }}" class="btn btn-sm btn-primary">
                Programar Primera Cita
            </a>
        </div>
    @else
        <div style="overflow-x: auto;">
            <table class="table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: var(--color-surface-hover); text-align: left; border-bottom: 1px solid var(--color-border);">
                        <th style="padding: 0.65rem 0.85rem; font-size: 0.75rem; color: var(--color-text-muted);">CÓDIGO FICHA</th>
                        <th style="padding: 0.65rem 0.85rem; font-size: 0.75rem; color: var(--color-text-muted);">FECHA Y HORA</th>
                        <th style="padding: 0.65rem 0.85rem; font-size: 0.75rem; color: var(--color-text-muted);">ESPECIALIDAD</th>
                        <th style="padding: 0.65rem 0.85rem; font-size: 0.75rem; color: var(--color-text-muted);">MÉDICO TRATANTE</th>
                        <th style="padding: 0.65rem 0.85rem; font-size: 0.75rem; color: var(--color-text-muted);">ESTADO</th>
                        <th style="padding: 0.65rem 0.85rem; font-size: 0.75rem; color: var(--color-text-muted); text-align: right;">COMPROBANTE</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($paciente->citas as $cita)
                        <tr style="border-bottom: 1px solid var(--color-border);">
                            <td style="padding: 0.75rem 0.85rem; font-family: monospace; font-weight: 700; color: var(--color-primary);">
                                {{ $cita->codigo_ficha }}
                            </td>
                            <td style="padding: 0.75rem 0.85rem;">
                                <strong>{{ $cita->fecha ? $cita->fecha->format('d/m/Y') : '' }}</strong>
                                <span style="color: var(--color-text-muted); font-size: 0.85rem;">a las {{ substr($cita->hora, 0, 5) }}</span>
                            </td>
                            <td style="padding: 0.75rem 0.85rem;">
                                {{ $cita->medico?->especialidad?->nombre ?? 'N/D' }}
                            </td>
                            <td style="padding: 0.75rem 0.85rem; font-weight: 500;">
                                {{ $cita->medico?->user?->name ?? 'N/D' }}
                            </td>
                            <td style="padding: 0.75rem 0.85rem;">
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
                                @endphp
                                <span class="badge {{ $badgeClass }}">{{ ucfirst(str_replace('_', ' ', $cita->estado)) }}</span>
                            </td>
                            <td style="padding: 0.75rem 0.85rem; text-align: right;">
                                <a href="{{ route('citas.show', $cita) }}" class="btn btn-sm btn-secondary" title="Ver Comprobante">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                    Ficha
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<!-- Modal para Registrar Nuevo Responsable Autorizado -->
<div id="modalResponsable" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 1rem;">
    <div class="card" style="width: 100%; max-width: 520px; padding: 1.5rem; margin: 0;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--color-primary); margin: 0;">
                Acreditar Responsable Autorizado
            </h3>
            <button type="button" onclick="document.getElementById('modalResponsable').style.display = 'none'" style="background: none; border: none; font-size: 1.25rem; cursor: pointer; color: var(--color-text-muted);">&times;</button>
        </div>

        <form method="POST" action="{{ route('pacientes.responsables.store', $paciente) }}">
            @csrf
            <div style="margin-bottom: 0.75rem;">
                <label class="form-label">Cédula del Tutor / Responsable *</label>
                <input type="text" name="ci" class="form-input" required placeholder="Ej. 4829103">
            </div>

            <div style="margin-bottom: 0.75rem;">
                <label class="form-label">Nombre Completo *</label>
                <input type="text" name="nombres" class="form-input" required placeholder="Ej. Roberto Gómez Salazar">
            </div>

            <div class="grid-2" style="margin-bottom: 0.75rem;">
                <div>
                    <label class="form-label">Parentesco *</label>
                    <select name="parentesco" class="form-input" required>
                        <option value="Padre">Padre</option>
                        <option value="Madre">Madre</option>
                        <option value="Tutor Legal">Tutor Legal</option>
                        <option value="Cónyuge">Cónyuge</option>
                        <option value="Hijo/a">Hijo/a</option>
                        <option value="Otro">Otro</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Teléfono *</label>
                    <input type="text" name="telefono" class="form-input" required placeholder="Ej. 77011223">
                </div>
            </div>

            <div style="margin-bottom: 1.25rem;">
                <label class="form-label">Fecha Límite de Vigencia de la Autorización *</label>
                <input type="date" name="fecha_limite_vigencia" class="form-input" required value="{{ now()->addYear()->toDateString() }}" min="{{ date('Y-m-d') }}">
                <small style="color: var(--color-text-muted); font-size: 0.75rem; display: block; margin-top: 0.25rem;">
                    Requerimiento CU7: El sistema advertirá cuando la autorización haya caducado.
                </small>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('modalResponsable').style.display = 'none'">
                    Cancelar
                </button>
                <button type="submit" class="btn btn-primary">
                    Guardar y Acreditar
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
