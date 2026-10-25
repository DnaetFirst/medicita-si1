@extends('layouts.app')

@section('content')
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 style="font-size: var(--font-2xl); font-weight: 700; color: var(--color-primary); margin-bottom: 0.25rem;">
            Bitácora de Auditoría (CU19)
        </h1>
        <p style="color: var(--color-text-muted); font-size: var(--font-sm);">
            Registro inmutable de transacciones, autenticación y trazabilidad del sistema
        </p>
    </div>
    <a href="{{ route('bitacora.export', request()->all()) }}" class="btn btn-secondary">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Exportar a CSV
    </a>
</div>

<!-- Filtros de Auditoría -->
<div class="card" style="padding: 1rem; margin-bottom: 1.5rem;">
    <form method="GET" action="{{ route('bitacora.index') }}" style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: flex-end;">
        <div style="flex: 1; min-width: 130px;">
            <label class="form-label" style="font-size: 0.75rem; margin-bottom: 0.25rem;">Desde</label>
            <input type="date" name="desde" class="form-input" value="{{ $filters['desde'] ?? '' }}" style="width: 100%;">
        </div>

        <div style="flex: 1; min-width: 130px;">
            <label class="form-label" style="font-size: 0.75rem; margin-bottom: 0.25rem;">Hasta</label>
            <input type="date" name="hasta" class="form-input" value="{{ $filters['hasta'] ?? '' }}" style="width: 100%;">
        </div>

        <div style="flex: 1; min-width: 150px;">
            <label class="form-label" style="font-size: 0.75rem; margin-bottom: 0.25rem;">Módulo</label>
            <select name="modulo" class="form-input" style="width: 100%;">
                <option value="">Todos los Módulos</option>
                @foreach($modulos as $m)
                    <option value="{{ $m }}" {{ ($filters['modulo'] ?? '') == $m ? 'selected' : '' }}>
                        {{ $m }}
                    </option>
                @endforeach
            </select>
        </div>

        <div style="flex: 1; min-width: 150px;">
            <label class="form-label" style="font-size: 0.75rem; margin-bottom: 0.25rem;">Usuario</label>
            <select name="user_id" class="form-input" style="width: 100%;">
                <option value="">Todos los Usuarios</option>
                @foreach($usuarios as $u)
                    <option value="{{ $u->id }}" {{ ($filters['user_id'] ?? '') == $u->id ? 'selected' : '' }}>
                        {{ $u->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div style="display: flex; gap: 0.5rem;">
            <button type="submit" class="btn btn-secondary">Filtrar</button>
            <a href="{{ route('bitacora.index') }}" class="btn btn-secondary" style="color: var(--color-text-muted);">Limpiar</a>
        </div>
    </form>
</div>

<!-- Tabla de Registros -->
<div class="card" style="padding: 0; overflow: hidden;">
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.8125rem;">
            <thead>
                <tr style="background: #F8FAFC; border-bottom: 1px solid var(--color-border); color: var(--color-text-muted); font-size: 0.75rem; text-transform: uppercase;">
                    <th style="padding: 0.875rem 1rem;">Fecha / Hora</th>
                    <th style="padding: 0.875rem 1rem;">Usuario / Rol</th>
                    <th style="padding: 0.875rem 1rem;">Módulo</th>
                    <th style="padding: 0.875rem 1rem;">Acción Ejecutada</th>
                    <th style="padding: 0.875rem 1rem;">Dirección IP</th>
                    <th style="padding: 0.875rem 1rem;">Detalles del Evento</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    <tr style="border-bottom: 1px solid var(--color-border);" onmouseover="this.style.background='#F8FAFC'" onmouseout="this.style.background='white'">
                        <td style="padding: 0.875rem 1rem; color: var(--color-text-muted); white-space: nowrap; font-family: monospace;">
                            {{ $log->created_at->format('d/m/Y H:i:s') }}
                        </td>
                        <td style="padding: 0.875rem 1rem;">
                            <div style="font-weight: 600; color: var(--color-primary);">{{ $log->usuario_nombre }}</div>
                            <span class="badge" style="background: #EDF2F7; font-size: 0.7rem; color: #4A5568;">{{ $log->rol }}</span>
                        </td>
                        <td style="padding: 0.875rem 1rem;">
                            <span class="badge badge-info" style="font-size: 0.7rem;">
                                {{ $log->modulo }}
                            </span>
                        </td>
                        <td style="padding: 0.875rem 1rem; font-weight: 600; color: {{ str_contains($log->accion, 'FALLIDO') || str_contains($log->accion, 'BLOQUEADO') ? 'var(--color-danger)' : (str_contains($log->accion, 'EXITOSO') ? 'var(--color-success)' : 'var(--color-primary)') }};">
                            {{ $log->accion }}
                        </td>
                        <td style="padding: 0.875rem 1rem; color: var(--color-text-muted); font-family: monospace; font-size: 0.75rem;">
                            {{ $log->ip_address ?? '127.0.0.1' }}
                        </td>
                        <td style="padding: 0.875rem 1rem; color: var(--color-text); font-family: monospace; font-size: 0.75rem; max-width: 300px; word-break: break-all;">
                            {{ $log->detalles ?: '—' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="padding: 2.5rem; text-align: center; color: var(--color-text-muted);">
                            No existen registros de auditoría que coincidan con la búsqueda.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($logs->hasPages())
        <div style="padding: 1rem; border-top: 1px solid var(--color-border);">
            {{ $logs->links() }}
        </div>
    @endif
</div>
@endsection
