@extends('layouts.app')

@section('content')
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 style="font-size: var(--font-2xl); font-weight: 700; color: var(--color-primary); margin-bottom: 0.25rem;">
            Fichas de Pacientes (CU7)
        </h1>
        <p style="color: var(--color-text-muted); font-size: var(--font-sm);">
            Registro clínico, datos de contacto, tutores y representantes legales autorizados
        </p>
    </div>
    <a href="{{ route('pacientes.create') }}" class="btn btn-primary" style="box-shadow: 0 4px 10px rgba(13, 124, 124, 0.2);">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Nuevo Paciente
    </a>
</div>

<!-- KPIs -->
<div class="grid-4" style="margin-bottom: 1.5rem;">
    <div class="card" style="margin-bottom: 0; padding: 1rem;">
        <span style="font-size: 0.75rem; color: var(--color-text-muted); font-weight: 600; text-transform: uppercase;">Total Pacientes</span>
        <div style="font-size: 1.75rem; font-weight: 700; color: var(--color-primary);">{{ $kpis['total'] }}</div>
    </div>
    <div class="card" style="margin-bottom: 0; padding: 1rem;">
        <span style="font-size: 0.75rem; color: var(--color-text-muted); font-weight: 600; text-transform: uppercase;">Pacientes Activos</span>
        <div style="font-size: 1.75rem; font-weight: 700; color: var(--color-success);">{{ $kpis['activos'] }}</div>
    </div>
    <div class="card" style="margin-bottom: 0; padding: 1rem;">
        <span style="font-size: 0.75rem; color: var(--color-text-muted); font-weight: 600; text-transform: uppercase;">Con Tutor / Autorizado</span>
        <div style="font-size: 1.75rem; font-weight: 700; color: var(--color-secondary);">{{ $kpis['con_responsable'] }}</div>
    </div>
    <div class="card" style="margin-bottom: 0; padding: 1rem;">
        <span style="font-size: 0.75rem; color: var(--color-text-muted); font-weight: 600; text-transform: uppercase;">Nuevos Este Mes</span>
        <div style="font-size: 1.75rem; font-weight: 700; color: var(--color-info);">{{ $kpis['nuevos_mes'] }}</div>
    </div>
</div>

<!-- Filtros -->
<div class="card" style="padding: 1rem; margin-bottom: 1.5rem;">
    <form method="GET" action="{{ route('pacientes.index') }}" style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
        <div style="flex: 2; min-width: 240px;">
            <input type="text" name="search" class="form-input" placeholder="Buscar por CI, nombres, apellidos o teléfono..." value="{{ $filters['search'] ?? '' }}" style="width: 100%;">
        </div>

        <div style="flex: 1; min-width: 140px;">
            <select name="estado" class="form-input" style="width: 100%;">
                <option value="">Todos los Estados</option>
                <option value="activo" {{ ($filters['estado'] ?? '') === 'activo' ? 'selected' : '' }}>Activos</option>
                <option value="inactivo" {{ ($filters['estado'] ?? '') === 'inactivo' ? 'selected' : '' }}>Inactivos</option>
            </select>
        </div>

        <button type="submit" class="btn btn-secondary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            Filtrar
        </button>

        @if(!empty($filters['search']) || !empty($filters['estado']))
            <a href="{{ route('pacientes.index') }}" class="btn btn-secondary" style="color: var(--color-text-muted);">
                Limpiar
            </a>
        @endif
    </form>
</div>

<!-- Tabla de Pacientes -->
<div class="card" style="padding: 0; overflow: hidden;">
    <div style="overflow-x: auto;">
        <table class="table" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: var(--color-surface-hover); text-align: left; border-bottom: 1px solid var(--color-border);">
                    <th style="padding: 0.85rem 1rem; font-size: 0.8rem; font-weight: 600; color: var(--color-text-muted);">PACIENTE</th>
                    <th style="padding: 0.85rem 1rem; font-size: 0.8rem; font-weight: 600; color: var(--color-text-muted);">CÉDULA (CI)</th>
                    <th style="padding: 0.85rem 1rem; font-size: 0.8rem; font-weight: 600; color: var(--color-text-muted);">EDAD / GÉNERO</th>
                    <th style="padding: 0.85rem 1rem; font-size: 0.8rem; font-weight: 600; color: var(--color-text-muted);">CONTACTO</th>
                    <th style="padding: 0.85rem 1rem; font-size: 0.8rem; font-weight: 600; color: var(--color-text-muted);">TUTOR / RESPONSABLE</th>
                    <th style="padding: 0.85rem 1rem; font-size: 0.8rem; font-weight: 600; color: var(--color-text-muted);">ESTADO</th>
                    <th style="padding: 0.85rem 1rem; font-size: 0.8rem; font-weight: 600; color: var(--color-text-muted); text-align: right;">ACCIONES</th>
                </tr>
            </thead>
            <tbody>
                @forelse($pacientes as $paciente)
                    <tr style="border-bottom: 1px solid var(--color-border); transition: background 0.15s ease;">
                        <td style="padding: 0.85rem 1rem;">
                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                <div style="width: 36px; height: 36px; border-radius: 50%; background: #e0f2fe; color: #0284c7; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem;">
                                    {{ strtoupper(substr($paciente->nombres, 0, 1) . substr($paciente->apellidos, 0, 1)) }}
                                </div>
                                <div>
                                    <a href="{{ route('pacientes.show', $paciente) }}" style="font-weight: 600; color: var(--color-primary); text-decoration: none;">
                                        {{ $paciente->nombre_completo }}
                                    </a>
                                    @if($paciente->grupo_sanguineo)
                                        <div style="font-size: 0.75rem; color: var(--color-danger); font-weight: 600;">
                                            Grupo: {{ $paciente->grupo_sanguineo }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td style="padding: 0.85rem 1rem; font-family: monospace; font-weight: 600;">
                            {{ $paciente->ci }}
                        </td>
                        <td style="padding: 0.85rem 1rem;">
                            <div style="font-weight: 500;">
                                {{ $paciente->fecha_nacimiento ? $paciente->fecha_nacimiento->age . ' años' : 'N/D' }}
                            </div>
                            <div style="font-size: 0.75rem; color: var(--color-text-muted);">
                                {{ $paciente->genero ?? 'No especificado' }}
                            </div>
                        </td>
                        <td style="padding: 0.85rem 1rem;">
                            <div>{{ $paciente->telefono }}</div>
                            <div style="font-size: 0.75rem; color: var(--color-text-muted);">{{ $paciente->email ?? 'Sin correo' }}</div>
                        </td>
                        <td style="padding: 0.85rem 1rem;">
                            @php
                                $responsableVigente = $paciente->responsableActivo;
                            @endphp
                            @if($responsableVigente)
                                <div style="display: flex; align-items: center; gap: 0.4rem;">
                                    <span class="badge badge-success" style="font-size: 0.7rem;">Vigente</span>
                                    <span style="font-weight: 500; font-size: 0.85rem;">{{ $responsableVigente->nombres }} ({{ $responsableVigente->parentesco }})</span>
                                </div>
                            @elseif($paciente->responsables->isNotEmpty())
                                <span class="badge badge-warning" style="font-size: 0.7rem;">Autorización Vencida</span>
                            @else
                                <span style="font-size: 0.8rem; color: var(--color-text-muted);">Titular (Sin tutor)</span>
                            @endif
                        </td>
                        <td style="padding: 0.85rem 1rem;">
                            <span class="badge {{ $paciente->activo ? 'badge-success' : 'badge-danger' }}">
                                {{ $paciente->activo ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td style="padding: 0.85rem 1rem; text-align: right;">
                            <div style="display: flex; justify-content: flex-end; gap: 0.4rem;">
                                <a href="{{ route('pacientes.show', $paciente) }}" class="btn btn-sm btn-secondary" title="Ver Ficha Integral">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                                </a>
                                <a href="{{ route('pacientes.edit', $paciente) }}" class="btn btn-sm btn-secondary" title="Editar Datos">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>
                                </a>
                                <form method="POST" action="{{ route('pacientes.toggle', $paciente) }}" style="display: inline;" onsubmit="return confirm('¿Cambiar estado de este paciente?');">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-sm {{ $paciente->activo ? 'btn-danger' : 'btn-success' }}" title="{{ $paciente->activo ? 'Dar de baja' : 'Reactivar' }}">
                                        @if($paciente->activo)
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="4.93" x2="19.07" y1="4.93" y2="19.07"/></svg>
                                        @else
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                                        @endif
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="padding: 3rem; text-align: center; color: var(--color-text-muted);">
                            No se encontraron pacientes que coincidan con la búsqueda.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($pacientes->hasPages())
        <div style="padding: 1rem; border-top: 1px solid var(--color-border);">
            {{ $pacientes->links() }}
        </div>
    @endif
</div>
@endsection
