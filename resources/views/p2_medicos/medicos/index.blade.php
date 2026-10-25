@extends('layouts.app')

@section('content')
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 style="font-size: var(--font-2xl); font-weight: 700; color: var(--color-primary); margin-bottom: 0.25rem;">
            Catálogo de Médicos (CU4)
        </h1>
        <p style="color: var(--color-text-muted); font-size: var(--font-sm);">
            Gestión de profesionales de la salud, matrículas y asignación de especialidades
        </p>
    </div>
    <a href="{{ route('medicos.create') }}" class="btn btn-primary" style="box-shadow: 0 4px 10px rgba(13, 124, 124, 0.2);">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Registrar Médico
    </a>
</div>

<!-- KPIs -->
<div class="grid-4" style="margin-bottom: 1.5rem;">
    <div class="card" style="margin-bottom: 0; padding: 1rem;">
        <span style="font-size: 0.75rem; color: var(--color-text-muted); font-weight: 600; text-transform: uppercase;">Total Médicos</span>
        <div style="font-size: 1.75rem; font-weight: 700; color: var(--color-primary);">{{ $kpis['total'] }}</div>
    </div>
    <div class="card" style="margin-bottom: 0; padding: 1rem;">
        <span style="font-size: 0.75rem; color: var(--color-text-muted); font-weight: 600; text-transform: uppercase;">Médicos Activos</span>
        <div style="font-size: 1.75rem; font-weight: 700; color: var(--color-success);">{{ $kpis['activos'] }}</div>
    </div>
    <div class="card" style="margin-bottom: 0; padding: 1rem;">
        <span style="font-size: 0.75rem; color: var(--color-text-muted); font-weight: 600; text-transform: uppercase;">Inactivos / De Baja</span>
        <div style="font-size: 1.75rem; font-weight: 700; color: var(--color-text-muted);">{{ $kpis['inactivos'] }}</div>
    </div>
    <div class="card" style="margin-bottom: 0; padding: 1rem;">
        <span style="font-size: 0.75rem; color: var(--color-text-muted); font-weight: 600; text-transform: uppercase;">Especialidades Cubiertas</span>
        <div style="font-size: 1.75rem; font-weight: 700; color: var(--color-secondary);">{{ $kpis['especialidades_cubiertas'] }}</div>
    </div>
</div>

<!-- Filtros -->
<div class="card" style="padding: 1rem; margin-bottom: 1.5rem;">
    <form method="GET" action="{{ route('medicos.index') }}" style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
        <div style="flex: 2; min-width: 220px;">
            <input type="text" name="search" class="form-input" placeholder="Buscar por nombre, CI o matrícula..." value="{{ $filters['search'] ?? '' }}" style="width: 100%;">
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

        <div style="flex: 1; min-width: 140px;">
            <select name="estado" class="form-input" style="width: 100%;">
                <option value="">Todos los Estados</option>
                <option value="activo" {{ ($filters['estado'] ?? '') === 'activo' ? 'selected' : '' }}>Activo</option>
                <option value="inactivo" {{ ($filters['estado'] ?? '') === 'inactivo' ? 'selected' : '' }}>Inactivo</option>
                <option value="de_baja" {{ ($filters['estado'] ?? '') === 'de_baja' ? 'selected' : '' }}>De Baja</option>
            </select>
        </div>

        <button type="submit" class="btn btn-secondary">Filtrar</button>
        <a href="{{ route('medicos.index') }}" class="btn btn-secondary" style="color: var(--color-text-muted);">Limpiar</a>
    </form>
</div>

<!-- Tabla de Médicos -->
<div class="card" style="padding: 0; overflow: hidden;">
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.875rem;">
            <thead>
                <tr style="background: #F8FAFC; border-bottom: 1px solid var(--color-border); color: var(--color-text-muted); font-size: 0.75rem; text-transform: uppercase;">
                    <th style="padding: 0.875rem 1.25rem;">Médico</th>
                    <th style="padding: 0.875rem 1.25rem;">Especialidad</th>
                    <th style="padding: 0.875rem 1.25rem;">Matrícula Profesional</th>
                    <th style="padding: 0.875rem 1.25rem;">Contacto</th>
                    <th style="padding: 0.875rem 1.25rem;">Estado</th>
                    <th style="padding: 0.875rem 1.25rem; text-align: right;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($medicos as $med)
                    <tr style="border-bottom: 1px solid var(--color-border);" onmouseover="this.style.background='#F8FAFC'" onmouseout="this.style.background='white'">
                        <td style="padding: 1rem 1.25rem;">
                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                <div style="width: 36px; height: 36px; border-radius: 50%; background: {{ $med->especialidad?->color ?? '#0D7C7C' }}20; color: {{ $med->especialidad?->color ?? '#0D7C7C' }}; display: flex; align-items: center; justify-content: center; font-weight: 700;">
                                    {{ strtoupper(substr($med->user?->name ?? 'Dr', 0, 2)) }}
                                </div>
                                <div>
                                    <div style="font-weight: 600; color: var(--color-primary);">{{ $med->user?->name }}</div>
                                    <div style="font-size: 0.75rem; color: var(--color-text-muted);">CI: {{ $med->user?->ci }}</div>
                                </div>
                            </div>
                        </td>
                        <td style="padding: 1rem 1.25rem;">
                            <span class="badge" style="background: {{ $med->especialidad?->color ?? '#0D7C7C' }}15; color: {{ $med->especialidad?->color ?? '#0D7C7C' }}; font-weight: 600;">
                                {{ $med->especialidad?->nombre }}
                            </span>
                        </td>
                        <td style="padding: 1rem 1.25rem; font-family: monospace; font-size: 0.8125rem;">
                            {{ $med->nro_registro_medico }}
                        </td>
                        <td style="padding: 1rem 1.25rem;">
                            <div style="color: var(--color-text);">{{ $med->telefono_contacto ?: ($med->user?->telefono ?? '—') }}</div>
                            <div style="font-size: 0.75rem; color: var(--color-text-muted);">{{ $med->user?->email }}</div>
                        </td>
                        <td style="padding: 1rem 1.25rem;">
                            @if($med->estado === 'activo')
                                <span class="badge badge-success">Activo</span>
                            @elseif($med->estado === 'inactivo')
                                <span class="badge" style="background: #E2E8F0; color: #718096;">Inactivo</span>
                            @else
                                <span class="badge badge-danger">De Baja</span>
                            @endif
                        </td>
                        <td style="padding: 1rem 1.25rem; text-align: right;">
                            <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                                <a href="{{ route('medicos.show', $med) }}" class="btn btn-secondary" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;" title="Ver Ficha y Horario">
                                    Ficha
                                </a>
                                <a href="{{ route('medicos.edit', $med) }}" class="btn btn-secondary" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                                    Editar
                                </a>
                                <form action="{{ route('medicos.toggle', $med) }}" method="POST" style="display: inline;">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-secondary" style="padding: 0.25rem 0.5rem; font-size: 0.75rem; color: {{ $med->estado === 'activo' ? 'var(--color-danger)' : 'var(--color-success)' }};">
                                        {{ $med->estado === 'activo' ? 'Pausar' : 'Activar' }}
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="padding: 2.5rem; text-align: center; color: var(--color-text-muted);">
                            No se encontraron médicos registrados con los criterios seleccionados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($medicos->hasPages())
        <div style="padding: 1rem; border-top: 1px solid var(--color-border);">
            {{ $medicos->links() }}
        </div>
    @endif
</div>
@endsection
